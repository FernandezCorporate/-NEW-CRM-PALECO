<?php

use App\Enums\ComplaintSources;
use App\Http\Requests\Tickets\StoreChildTicketRequest;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\UpdateTicketIntakeRequest;
use App\Models\Department;
use App\Models\TicketCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    config(['database.default' => 'purok_street_test', 'database.connections.purok_street_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ]]);

    Schema::create('activity_log', function (Blueprint $table) {
        $table->id();
        $table->string('log_name')->nullable();
        $table->text('description');
        $table->string('subject_type')->nullable();
        $table->string('subject_id')->nullable();
        $table->string('causer_type')->nullable();
        $table->string('causer_id')->nullable();
        $table->string('event')->nullable();
        $table->json('attribute_changes')->nullable();
        $table->json('properties')->nullable();
        $table->timestamps();
    });

    Schema::create('departments', function (Blueprint $table) {
        $table->id();
        $table->string('dept_name');
        $table->timestamps();
    });

    Schema::create('ticket_categories', function (Blueprint $table) {
        $table->id();
        $table->string('category_name');
        $table->softDeletes();
        $table->timestamps();
    });

    $this->dept = Department::create(['dept_name' => 'Technical Services']);
    $this->category = TicketCategory::create(['category_name' => 'Line Fault']);
});

test('StoreTicketRequest enforces purok and street as mandatory fields', function () {
    $rules = (new StoreTicketRequest())->rules();
    $messages = (new StoreTicketRequest())->messages();

    $baseData = [
        'consumer_contact' => '09123456789',
        'complaint_source' => ComplaintSources::WALK_IN->value,
        'complaint_description' => 'Power outage on main feeder.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'other_category' => false,
    ];

    // Missing both purok and street
    $validator = Validator::make($baseData, $rules, $messages);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('purok'))->toBeTrue()
        ->and($validator->errors()->has('street'))->toBeTrue()
        ->and($validator->errors()->first('purok'))->toBe('The Purok field is required for location routing.')
        ->and($validator->errors()->first('street'))->toBe('The Street field is required for location routing.');

    // Missing only street
    $validatorWithPurokOnly = Validator::make(array_merge($baseData, ['purok' => 'Purok Bayanihan']), $rules, $messages);
    expect($validatorWithPurokOnly->fails())->toBeTrue()
        ->and($validatorWithPurokOnly->errors()->has('purok'))->toBeFalse()
        ->and($validatorWithPurokOnly->errors()->has('street'))->toBeTrue();

    // Missing only purok
    $validatorWithStreetOnly = Validator::make(array_merge($baseData, ['street' => 'Rizal Avenue']), $rules, $messages);
    expect($validatorWithStreetOnly->fails())->toBeTrue()
        ->and($validatorWithStreetOnly->errors()->has('purok'))->toBeTrue()
        ->and($validatorWithStreetOnly->errors()->has('street'))->toBeFalse();

    // Both provided
    $validatorComplete = Validator::make(array_merge($baseData, [
        'purok' => 'Purok Bayanihan',
        'street' => 'Rizal Avenue',
    ]), $rules, $messages);
    expect($validatorComplete->passes())->toBeTrue();
});

test('StoreChildTicketRequest enforces purok and street as mandatory fields', function () {
    $rules = (new StoreChildTicketRequest())->rules();
    $messages = (new StoreChildTicketRequest())->messages();

    $baseData = [
        'department_id' => $this->dept->id,
        'complaint_description' => 'Child ticket task description.',
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'other_category' => false,
    ];

    $validator = Validator::make($baseData, $rules, $messages);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('purok'))->toBeTrue()
        ->and($validator->errors()->has('street'))->toBeTrue()
        ->and($validator->errors()->first('purok'))->toBe('The Purok field is required for location routing.')
        ->and($validator->errors()->first('street'))->toBe('The Street field is required for location routing.');

    $validatorComplete = Validator::make(array_merge($baseData, [
        'purok' => 'Purok 3',
        'street' => 'National Highway',
    ]), $rules, $messages);
    expect($validatorComplete->passes())->toBeTrue();
});

test('UpdateTicketIntakeRequest enforces purok and street as mandatory fields', function () {
    $rules = (new UpdateTicketIntakeRequest())->rules();
    $messages = (new UpdateTicketIntakeRequest())->messages();

    $baseData = [
        'consumer_contact' => '09123456789',
        'complaint_source' => ComplaintSources::PHONE_CALL->value,
        'complaint_description' => 'Corrected complaint details.',
        'department_id' => $this->dept->id,
        'category_id' => $this->category->id,
        'barangay' => 'Tiniguiban',
        'other_category' => false,
    ];

    $validator = Validator::make($baseData, $rules, $messages);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('purok'))->toBeTrue()
        ->and($validator->errors()->has('street'))->toBeTrue()
        ->and($validator->errors()->first('purok'))->toBe('The Purok field is required for location routing.')
        ->and($validator->errors()->first('street'))->toBe('The Street field is required for location routing.');

    $validatorComplete = Validator::make(array_merge($baseData, [
        'purok' => 'Purok 5',
        'street' => 'Malvar Street',
    ]), $rules, $messages);
    expect($validatorComplete->passes())->toBeTrue();
});
