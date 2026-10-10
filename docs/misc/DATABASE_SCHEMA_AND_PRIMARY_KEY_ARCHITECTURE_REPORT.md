# PALECO OTRS-CRM: Database Schema & Primary Key Architecture Audit

**Document Status:** Architectural Evaluation & Decision Framework  
**Author:** Antigravity AI Engineering Assistant (Pair Programming Audit)  
**Target System:** PALECO OTRS-CRM Database Engine (MySQL 8.0+ / InnoDB)  
**Evaluation Rigor:** Strict / Zero-Compromise Technical Review  

---

## 1. Executive Summary & Architecture Scorecard

| Architectural Dimension | Current Rating | Verdict |
| :--- | :---: | :--- |
| **Primary Key Strategy** | **C+** | **Fractured.** Good use of ULIDs on some domain models, but crippled by identity schizophrenia (`system_id` on tickets vs `id` everywhere else). |
| **Foreign Key Consistency** | **D** | **High Technical Debt.** Inconsistent data types (`ulid` vs raw `char(26)`), arbitrary naming (`created_by` vs `accomplished_by_id`), and missing foreign key constraints (`approved_by_id`, `suggested_department_id`). |
| **Indexing & Performance** | **B+** | Composite performance indexes on `tickets` are well-structured for dashboard filtering. |
| **Schema Normalization** | **B** | Solid organizational domain boundaries (departments, teams, roles, tickets, accomplishments). |
| **Storage & Data Integrity** | **C** | Unbounded `VARCHAR(255)` for statuses, missing schema columns causing silent attribute drops (`accomplishment_photos`). |

### Direct Answers to Your Core Concerns

> **1. "I fear that the naming of the Primary Key Column as `id` is not very good."**  
> **Verdict: Your fear is unfounded.**  
> Naming the primary key `id` is the universal gold standard across relational database engineering, Eloquent ORM, Doctrine, Ruby on Rails, Django, and modern REST/JSON:API specifications.  
> The actual anti-pattern in your project is that you broke your own convention on your most critical table: naming it `system_id` on `tickets` while using `id` on every other table. This single inconsistency forces verbose relation overrides, breaks Laravel conventions, and creates perpetual confusion. Every table's primary key should simply be named `id`.

> **2. "Should I change them all into UUID and remove auto-incrementing IDs and even the use of ULIDs?"**  
> **Verdict: Absolutely NOT. That would be an architectural regression.**  
> Replacing everything with standard random UUIDv4 in MySQL InnoDB would severely degrade write performance due to B-Tree index fragmentation and page splits. Furthermore, replacing integer IDs on small lookup tables (`departments`, `roles`, `categories`) with UUIDs is cargo-cult architecture that bloats memory with zero benefit.  
> Your adoption of **ULIDs** for core domain entities (`tickets`, `users`, `teams`, `consumers`) is actually **optimal for your offline mobile sync requirements** because ULIDs are time-ordered and monotonically increasing. The problem is not ULID—the problem is how haphazardly and inconsistently it was implemented across foreign keys.

---

## 2. The Primary Key Dilemma: Auto-Increment vs. ULID vs. UUIDv4 vs. UUIDv7

To understand why a "one-size-fits-all" primary key strategy is an anti-pattern, consider how MySQL's **InnoDB Storage Engine** physically writes data to disk.

```
InnoDB Clustered Index (B+ Tree) Insertion Behavior:

[Sequential Key: BIGINT / ULID / UUIDv7]
Page 1: [Row 1] -> [Row 2] -> [Row 3] -> [Row 4] (Full)
Page 2: [Row 5] -> [Row 6] -> [Row 7] -> [Row 8] (Appended to Right - 0 Page Splits)
Result: 100% Page Fill Factor, Sequential Disk I/O, Maximum Cache Locality.

[Random Key: UUIDv4]
Page 1: [Row 1] ──(Insert Row 2.5)──> Page 1 Splits into Page 1A & 1B!
Page 1A: [Row 1] -> [Row 2] (50% Empty)
Page 1B: [Row 2.5] -> [Row 3] (50% Empty)
Result: Massive Index Fragmentation, 50% Memory Waste, Heavy Random Disk I/O.
```

### Comprehensive Identification Strategy Comparison

| Metric / Dimension | Auto-Increment `BIGINT` | Random `UUIDv4` | Sortable `ULID` (Current) | Sortable `UUIDv7` (RFC 9562) |
| :--- | :---: | :---: | :---: | :---: |
| **Storage Footprint** | **8 Bytes** (64-bit int) | 36 Bytes (String) or 16 Bytes (Binary) | **26 Bytes** (Base32 String) or 16 Bytes (Binary) | 36 Bytes (UUID String) or 16 Bytes (Binary) |
| **InnoDB B+ Tree Clustering** | **Optimal** (Sequential append) | **Catastrophic** (Random inserts cause page splits) | **Optimal** (Time-prefixed monotonic append) | **Optimal** (Time-prefixed monotonic append) |
| **Offline Generation** | ❌ Impossible (Requires central DB lock) | ✅ Instant on device | ✅ **Instant on device** | ✅ Instant on device |
| **IDOR / Enumeration Security** | ❌ Vulnerable (Predictable sequence: `1, 2, 3`) | ✅ Cryptographically unpredictable | ✅ Unpredictable (80 bits randomness) | ✅ Unpredictable (74 bits randomness) |
| **URL & Human Readability** | High (`/tickets/104`) | Low (`c4a760a8-dbcf-4e5e...`) | **High** (`01HXYZ789...` clean Base32) | Moderate (Dashed string) |
| **Join & CPU Efficiency** | **1 CPU Cycle** (Register integer compare) | High CPU cost (String comparison) | Moderate (Base32 character compare) | Moderate (String compare) |
| **Best Used For** | **Lookup & Append-only Log Tables** | Distributed systems with no time ordering | **Domain Entities & Offline Sync** | Modern Enterprise Web APIs |

### Why Auto-Increment IDs Must Be Retained on Lookup Tables
Tables like `departments`, `account_roles`, `team_roles`, and `ticket_categories` are static dictionaries that will never exceed 100 rows in PALECO's lifetime.
* Using `BIGINT`/`INT` takes 4–8 bytes.
* An integer join (`JOIN departments ON tickets.department_id = departments.id`) executes at native hardware speed.
* Turning `departments.id` into a UUID or ULID adds zero security (nobody attacks a system by guessing department IDs `1` vs `2`), while quadrupling foreign key index storage on the `tickets` table.

### Why ULID Is Superior to UUIDv4 for PALECO Field Operations
In remote Palawan locations where linemen have zero network connection:
1. Linemen create offline actions (`start`, `accomplish`).
2. The phone generates a **ULID** locally.
3. The first 48 bits encode the exact millisecond the action occurred, followed by 80 bits of cryptographically secure random entropy.
4. When uploaded to MySQL, the ULID inserts monotonically into InnoDB's clustered index without splitting B-Tree nodes.
5. Random UUIDv4 lacks this time ordering and causes heavy index fragmentation once tables grow into hundreds of thousands of rows.

---

## 3. The Harsh Schema Audit: Flaws, Inconsistencies & Anti-Patterns

### Flaw 1: The `system_id` Disaster on `tickets`
In [`database/migrations/2026_09_24_135916_create_ticket_module_tables.php`](file:///c:/Users/allen/Desktop/Capstone/-NEW-CRM-PALECO/PALECO_OTRS-CRM/database/migrations/2026_09_24_135916_create_ticket_module_tables.php#L20):
```php
// THE CRIME:
Schema::create('tickets', function (Blueprint $table) {
    $table->ulid('system_id')->primary();
    $table->string('ticket_number')->unique();
    ...
```
* **Why did you do this?** You likely thought: *"I already have `ticket_number` (e.g., 2026-10-0001), so calling the primary key `id` might confuse me."*
* **The Consequences Across Your Codebase:**
  1. You broke Laravel convention. Eloquent assumes every primary key is `id`. You were forced to add `protected $primaryKey = 'system_id';` in `Ticket.php`.
  2. Every single foreign key referencing `tickets` is forced to explicitly write the target column:
     `$table->foreignUlid('parent_ticket_id')->constrained('tickets', 'system_id');`
     `$table->foreignUlid('ticket_id')->constrained('tickets', 'system_id');`
  3. In relationships across `TicketAssignment`, `TicketEndorsement`, and `TicketStatusLog`, you had to write:
     `return $this->belongsTo(Ticket::class, 'ticket_id', 'system_id');`
  4. In API resources and frontend templates, developers constantly second-guess whether to use `ticket.id`, `ticket.system_id`, or `ticket.ticket_number`.

**The Fix:** Rename `system_id` to `id`. Keep `ticket_number` as the human-facing reference code.

---

### Flaw 2: The Foreign Key Type Fracture
Look at how foreign keys are declared in `ticket_assignments`:
```php
Schema::create('ticket_assignments', function (Blueprint $table) {
    $table->id();
    $table->char('ticket_id', 26);    // <── Style 1: Raw char(26)
    $table->char('team_id', 26);      // <── Style 1: Raw char(26)
    $table->ulid('assigned_by');       // <── Style 2: Blueprint ulid() macro
```
In the **exact same table**, foreign keys pointing to ULID tables are declared in two completely different ways!
Then look at `ticket_status_logs`:
```php
$table->foreignUlid('ticket_id')->constrained('tickets', 'system_id'); // <── Style 3: foreignUlid macro
```
And in `ticket_endorsements`:
```php
$table->char('ticket_id', 26);
$table->char('created_by', 26);
$table->char('reviewed_by', 26)->nullable();
```
**Verdict:** This is sloppy migration authoring. You have three distinct syntax styles across five tables for the identical data type. All ULID foreign keys should consistently use `$table->foreignUlid('column_name')->constrained(...)`.

---

### Flaw 3: Dangerous Missing Foreign Key Constraints (Orphan Data Risk)
In `ticket_endorsements`:
```php
$table->unsignedBigInteger('suggested_department_id')->nullable();
// Notice what is missing? NO ->constrained('departments')!
```
In `ticket_accomplishments`:
```php
$table->char('approved_by_id', 26)->nullable();
// Notice what is missing? NO ->constrained('users', 'id')!
```
* **Impact:** `suggested_department_id` and `approved_by_id` are completely unconstrained at the database level! You can insert department ID `99999` or user ID `GARBAGE_ID` and MySQL will happily commit the transaction. If a user or department is deleted, these fields retain invalid dangling references.

---

### Flaw 4: Inconsistent Foreign Key Column Naming
Examine how user relationships are named across tables:
* In `tickets`: `created_by` (NO `_id` suffix)
* In `ticket_status_logs`: `changed_by` (NO `_id` suffix)
* In `ticket_assignments`: `assigned_by` (NO `_id` suffix)
* In `ticket_endorsements`: `created_by` and `reviewed_by` (NO `_id` suffix)
* In `ticket_accomplishments`: `accomplished_by_id`, `approved_by_id`, `rejected_by_id` (**WITH** `_id` suffix!)

**Verdict:** Inconsistency breeds bugs. Half your tables omit `_id` for user references while the other half append `_id`. Eloquent expects foreign keys to end in `_id`. When you name a column `assigned_by`, Eloquent's default relation resolution looks for `assigned_by_id` unless manually overridden.

---

### Flaw 5: Schema Discrepancy & Silent Data Drop in `accomplishment_photos`
In `database/migrations/2026_09_24_135916_create_ticket_module_tables.php`:
```php
Schema::create('accomplishment_photos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('accomplishment_id')->constrained('ticket_accomplishments')->cascadeOnDelete();
    $table->string('file_path');
    $table->timestamps();
});
```
Now look at your backend service in `TicketAccomplishmentService.php`:
```php
$report->photos()->create([
    'file_path' => $path,
    'file_name' => $photo->getClientOriginalName(),
    'file_size' => $photo->getSize(),
    'mime_type' => $photo->getMimeType(),
]);
```
* **The Reality:** Your service code is attempting to save `file_name`, `file_size`, and `mime_type` on every uploaded evidence photo. But because those columns **do not exist** in the migration, they are silently discarded! If you ever need to inspect file sizes or original names, the data is permanently lost.

---

### Flaw 6: Unbounded String Columns for State Machines
In `tickets`, `ticket_endorsements`, and `ticket_accomplishments`:
```php
$table->string('status')->default('open');
```
* In MySQL, `$table->string('status')` creates a `VARCHAR(255)`.
* Your status fields represent strict state-machine enumerations with values like `open`, `assigned`, `in_progress`, `resolved`, `closed` (maximum 15 characters).
* Allocating 255 bytes per row for a status column bloats index memory on tables with compound indexes like `['status', 'reported_at']`. Status columns should be explicitly sized (e.g. `VARCHAR(20)`) or backed by database ENUMs / clean string constants.

---

## 4. The Optimal Architecture Blueprint

Here is the clean, cohesive target schema model. Notice the strict consistency:
1. **Every primary key is named `id`.**
2. **Every foreign key is named with `_id` suffix.**
3. **Lookup tables use `BIGINT` auto-increment; Domain entities use `ULID`.**
4. **All foreign keys have explicit constraints and cascade/null rules.**

### Recommended Entity Relationship Model

```
┌────────────────────┐          ┌───────────────────────┐          ┌─────────────────────────┐
│    departments     │          │     account_roles     │          │       team_roles        │
├────────────────────┤          ├───────────────────────┤          ├─────────────────────────┤
│ id (BIGINT Auto)   │          │ id (BIGINT Auto)      │          │ id (BIGINT Auto)        │
│ dept_name (VARCHAR)│          │ role_name (VARCHAR)   │          │ role_name (VARCHAR)     │
└─────────┬──────────┘          │ slug_identifier (STR) │          │ slug_identifier (STR)   │
          │                     └───────────┬───────────┘          └────────────┬────────────┘
          │ 1:N                             │ 1:N                               │ 1:N
          ▼                                 ▼                                   ▼
┌────────────────────────────────────────────────────────────────────────────────────────────┐
│                                           users                                            │
├────────────────────────────────────────────────────────────────────────────────────────────┤
│ id (ULID Primary)                                                                          │
│ username, first_name, last_name, email, contact, password, is_active                       │
│ role_id (BIGINT FK -> account_roles.id)                                                    │
│ department_id (BIGINT FK -> departments.id)                                                │
└─────────────────────────────────────────────┬──────────────────────────────────────────────┘
                                              │ 1:N
                                              ▼
┌────────────────────────────────────────────────────────────────────────────────────────────┐
│                                          tickets                                           │
├────────────────────────────────────────────────────────────────────────────────────────────┤
│ id (ULID Primary)  <────────────────────────────────────── Standardized from system_id     │
│ ticket_number (VARCHAR 30 Unique)                                                          │
│ parent_ticket_id (ULID Nullable FK -> tickets.id)                                          │
│ consumer_id (ULID Nullable FK -> consumers.id)                                             │
│ category_id (BIGINT FK -> ticket_categories.id)                                            │
│ department_id (BIGINT FK -> departments.id)                                                │
│ team_id (ULID Nullable FK -> teams.id)                                                     │
│ created_by_id (ULID FK -> users.id)  <────────────────── Standardized from created_by     │
│ status (VARCHAR 20)                                                                        │
│ is_offline_synced (BOOLEAN), synced_at (TIMESTAMP)                                         │
│ reported_at, started_at, resolved_at, closed_at                                            │
└─────────────────────────────────────────────┬──────────────────────────────────────────────┘
          │                                   │                                  │
          │ 1:N                               │ 1:N                              │ 1:N
          ▼                                   ▼                                  ▼
┌──────────────────────────┐    ┌──────────────────────────┐    ┌────────────────────────────┐
│    ticket_assignments    │    │   ticket_endorsements    │    │   ticket_accomplishments   │
├──────────────────────────┤    ├──────────────────────────┤    ├────────────────────────────┤
│ id (BIGINT Auto)         │    │ id (ULID Primary)        │    │ id (ULID Primary)          │
│ ticket_id (ULID FK)      │    │ ticket_id (ULID FK)      │    │ ticket_id (ULID FK)        │
│ team_id (ULID FK)        │    │ created_by_id (ULID FK)  │    │ accomplished_by_id (ULID)  │
│ assigned_by_id (ULID FK) │    │ reviewed_by_id (ULID FK) │    │ approved_by_id (ULID FK)   │
│ reason (TEXT)            │    │ suggested_dept_id (FK)   │    │ rejected_by_id (ULID FK)   │
│ unassigned_at (TIMESTAMP)│    │ status (VARCHAR 20)      │    │ remarks, signature_path    │
└──────────────────────────┘    └──────────────────────────┘    └──────────────┬─────────────┘
                                                                               │ 1:N
                                                                               ▼
                                                                ┌────────────────────────────┐
                                                                │   accomplishment_photos    │
                                                                ├────────────────────────────┤
                                                                │ id (BIGINT Auto)           │
                                                                │ accomplishment_id (ULID FK)│
                                                                │ file_path, file_name       │
                                                                │ file_size, mime_type       │
                                                                └────────────────────────────┘
```

---

## 5. Cleaned Migration Code Blueprint

Here is how your core migrations should look when cleaned of technical debt:

### Table: `tickets` (Cleaned)
```php
Schema::create('tickets', function (Blueprint $table) {
    // 1. Standardized Primary Key
    $table->ulid('id')->primary();
    $table->string('ticket_number', 30)->unique();

    // 2. Self-referencing & Consumer Links
    $table->foreignUlid('parent_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
    $table->foreignUlid('consumer_id')->nullable()->constrained('consumers')->nullOnDelete();

    // 3. Contact & Complaint Details
    $table->string('consumer_contact', 20)->nullable();
    $table->string('complaint_source', 30);
    $table->text('complaint_description')->nullable();

    // 4. Categorization
    $table->foreignId('category_id')->nullable()->constrained('ticket_categories');
    $table->boolean('other_category')->default(false);
    $table->string('other_category_name')->nullable();

    // 5. Geographic Location
    $table->string('purok')->nullable();
    $table->string('street')->nullable();
    $table->string('barangay');
    $table->string('landmark')->nullable();

    // 6. Department & Team Routing
    $table->foreignId('department_id')->nullable()->constrained('departments');
    $table->foreignUlid('team_id')->nullable()->constrained('teams');
    $table->foreignUlid('created_by_id')->constrained('users'); // Cleaned naming

    // 7. Status & Offline Sync
    $table->string('status', 20)->default('open');
    $table->boolean('is_offline_synced')->default(false);
    $table->timestamp('synced_at')->nullable();

    // 8. Timestamps
    $table->timestamp('reported_at')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamp('closed_at')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

### Table: `ticket_assignments` (Cleaned)
```php
Schema::create('ticket_assignments', function (Blueprint $table) {
    $table->id();
    // Cleaned: Consistent foreignUlid macros
    $table->foreignUlid('ticket_id')->constrained('tickets')->cascadeOnDelete();
    $table->foreignUlid('team_id')->constrained('teams')->cascadeOnDelete();
    $table->foreignUlid('assigned_by_id')->constrained('users')->cascadeOnDelete();
    
    $table->text('reason')->nullable();
    $table->timestamp('unassigned_at')->nullable();
    $table->timestamps();
});
```

### Table: `ticket_endorsements` (Cleaned)
```php
Schema::create('ticket_endorsements', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('ticket_id')->constrained('tickets')->cascadeOnDelete();
    $table->foreignUlid('created_by_id')->constrained('users')->cascadeOnDelete();
    
    // Cleaned: Explicit foreign key constraint on suggested department
    $table->foreignId('suggested_department_id')->nullable()->constrained('departments')->nullOnDelete();

    $table->text('reason');
    $table->string('status', 20)->default('pending');
    $table->string('pre_endorsement_status', 20);
    $table->text('rejection_reason')->nullable();
    
    // Cleaned: Explicit foreign key on reviewer
    $table->foreignUlid('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamps();
});
```

### Table: `accomplishment_photos` (Cleaned)
```php
Schema::create('accomplishment_photos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('accomplishment_id')->constrained('ticket_accomplishments')->cascadeOnDelete();
    $table->string('file_path');
    
    // Cleaned: Added missing metadata columns
    $table->string('file_name')->nullable();
    $table->unsignedInteger('file_size')->nullable(); // Size in bytes
    $table->string('mime_type', 50)->nullable();
    $table->timestamps();
});
```

---

## 6. Strategic Refactoring Roadmap

If you decide to clean up this technical debt, execute it in three controlled phases to prevent regression:

### Phase 1: Zero-Downtime Safe Fixes (Immediate)
These changes fix bugs and missing constraints without breaking any existing frontend or mobile API contracts:
1. Add missing metadata columns (`file_name`, `file_size`, `mime_type`) to `accomplishment_photos`.
2. Add foreign key constraint to `ticket_endorsements.suggested_department_id`.
3. Add foreign key constraint to `ticket_accomplishments.approved_by_id`.
4. Add the offline sync audit columns (`is_offline_synced`, `synced_at`) from the offline architecture plan.

### Phase 2: Foreign Key Standardizing (Internal Backend)
1. Standardize raw `char(26)` declarations in `ticket_assignments` and `ticket_endorsements` to native `$table->foreignUlid()`.
2. Standardize column names with `_id` suffix:
   * `tickets.created_by` ➔ `tickets.created_by_id`
   * `ticket_assignments.assigned_by` ➔ `ticket_assignments.assigned_by_id`
   * `ticket_endorsements.created_by` ➔ `ticket_endorsements.created_by_id`
   * `ticket_endorsements.reviewed_by` ➔ `ticket_endorsements.reviewed_by_id`

### Phase 3: The `system_id` ➔ `id` Normalization (Major Release)
1. Rename `tickets.system_id` to `tickets.id`.
2. Update foreign key references in child tables (`ticket_id`).
3. Remove `protected $primaryKey = 'system_id';` from `Ticket.php`.
4. Remove verbose third-argument overrides from relationships (`belongsTo(Ticket::class, 'ticket_id', 'system_id')` ➔ `belongsTo(Ticket::class)`).

---

## 7. Summary Decision Matrix for the Team

| Question / Proposal | Recommendation | Rationale |
| :--- | :---: | :--- |
| **Rename Primary Keys from `id` to entity names (e.g. `user_id`, `dept_id`)?** | **NO** | Anti-pattern. Violates Laravel and REST standards. Causes duplicate naming in foreign keys. |
| **Replace all Primary Keys with random UUIDv4?** | **NO** | Causes severe InnoDB index fragmentation and random disk I/O. Destroys write throughput. |
| **Keep ULIDs for Domain Entities (Users, Teams, Tickets, Consumers)?** | **YES** | Perfect fit. Time-ordered, monotonic, no B-tree splits, safe for offline mobile generation. |
| **Keep Auto-Increment BIGINT for Dictionary Tables (Roles, Depts, Categories)?** | **YES** | Maximum CPU efficiency, zero storage waste, sub-millisecond joins. |
| **Rename `tickets.system_id` to `tickets.id`?** | **YES (High Priority)** | Eliminates identity schizophrenia across the entire application. |
| **Enforce Foreign Key Constraints on `approved_by_id` & `suggested_dept_id`?** | **YES (Critical)** | Prevents silent data corruption and orphan records. |

---

*Report filed in `docs/DATABASE_SCHEMA_AND_PRIMARY_KEY_ARCHITECTURE_REPORT.md`.*

