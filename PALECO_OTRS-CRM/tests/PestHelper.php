<?php

/**
 * IDE Helper for Pest PHP and Intelephense.
 *
 * Provides static analysis type-hinting for Pest closures without runtime impact.
 */

namespace Pest\PendingCalls {
    use Tests\TestCase;

    if (false) {
        /**
         * @mixin TestCase
         * @mixin \Illuminate\Foundation\Testing\TestCase
         */
        class TestCall {}

        /**
         * @mixin TestCase
         * @mixin \Illuminate\Foundation\Testing\TestCase
         */
        class BeforeEachCall {}

        /**
         * @mixin TestCase
         * @mixin \Illuminate\Foundation\Testing\TestCase
         */
        class AfterEachCall {}
    }
}
