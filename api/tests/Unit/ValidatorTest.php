<?php

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    // ── required ──────────────────────────────────────────────────────────────

    public function test_required_passes_with_value(): void
    {
        $v = Validator::make(['name' => 'Acme'], ['name' => 'required']);
        $this->assertFalse($v->fails());
    }

    public function test_required_fails_on_empty_string(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function test_required_fails_on_null(): void
    {
        $v = Validator::make([], ['name' => 'required']);
        $this->assertTrue($v->fails());
    }

    public function test_required_fails_on_empty_array(): void
    {
        $v = Validator::make(['items' => []], ['items' => 'required']);
        $this->assertTrue($v->fails());
    }

    // ── email ─────────────────────────────────────────────────────────────────

    public function test_email_passes_valid(): void
    {
        $v = Validator::make(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertFalse($v->fails());
    }

    public function test_email_fails_invalid(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertTrue($v->fails());
    }

    public function test_email_skips_empty(): void
    {
        $v = Validator::make(['email' => ''], ['email' => 'email']);
        $this->assertFalse($v->fails());
    }

    // ── numeric ───────────────────────────────────────────────────────────────

    public function test_numeric_passes_integer(): void
    {
        $v = Validator::make(['amount' => '100'], ['amount' => 'numeric']);
        $this->assertFalse($v->fails());
    }

    public function test_numeric_passes_float(): void
    {
        $v = Validator::make(['amount' => '18.5'], ['amount' => 'numeric']);
        $this->assertFalse($v->fails());
    }

    public function test_numeric_fails_string(): void
    {
        $v = Validator::make(['amount' => 'abc'], ['amount' => 'numeric']);
        $this->assertTrue($v->fails());
    }

    // ── integer ───────────────────────────────────────────────────────────────

    public function test_integer_passes(): void
    {
        $v = Validator::make(['qty' => '5'], ['qty' => 'integer']);
        $this->assertFalse($v->fails());
    }

    public function test_integer_fails_float(): void
    {
        $v = Validator::make(['qty' => '5.5'], ['qty' => 'integer']);
        $this->assertTrue($v->fails());
    }

    // ── min / max ─────────────────────────────────────────────────────────────

    public function test_min_passes(): void
    {
        $v = Validator::make(['price' => '10'], ['price' => 'min:1']);
        $this->assertFalse($v->fails());
    }

    public function test_min_fails_below(): void
    {
        $v = Validator::make(['price' => '0'], ['price' => 'min:1']);
        $this->assertTrue($v->fails());
    }

    public function test_max_passes(): void
    {
        $v = Validator::make(['rate' => '28'], ['rate' => 'max:28']);
        $this->assertFalse($v->fails());
    }

    public function test_max_fails_above(): void
    {
        $v = Validator::make(['rate' => '30'], ['rate' => 'max:28']);
        $this->assertTrue($v->fails());
    }

    // ── min_length / max_length ───────────────────────────────────────────────

    public function test_min_length_passes(): void
    {
        $v = Validator::make(['password' => 'secret123'], ['password' => 'min_length:8']);
        $this->assertFalse($v->fails());
    }

    public function test_min_length_fails(): void
    {
        $v = Validator::make(['password' => 'abc'], ['password' => 'min_length:8']);
        $this->assertTrue($v->fails());
    }

    public function test_max_length_passes(): void
    {
        $v = Validator::make(['gstin' => '22AAAAA0000A1Z5'], ['gstin' => 'max_length:15']);
        $this->assertFalse($v->fails());
    }

    public function test_max_length_fails(): void
    {
        $v = Validator::make(['gstin' => '22AAAAA0000A1Z5X'], ['gstin' => 'max_length:15']);
        $this->assertTrue($v->fails());
    }

    // ── date ──────────────────────────────────────────────────────────────────

    public function test_date_passes_iso(): void
    {
        $v = Validator::make(['due_date' => '2025-03-31'], ['due_date' => 'date']);
        $this->assertFalse($v->fails());
    }

    public function test_date_fails_invalid(): void
    {
        $v = Validator::make(['due_date' => 'not-a-date'], ['due_date' => 'date']);
        $this->assertTrue($v->fails());
    }

    // ── in ────────────────────────────────────────────────────────────────────

    public function test_in_passes_allowed_value(): void
    {
        $v = Validator::make(['type' => 'intra'], ['type' => 'in:intra,inter']);
        $this->assertFalse($v->fails());
    }

    public function test_in_fails_unknown_value(): void
    {
        $v = Validator::make(['type' => 'export'], ['type' => 'in:intra,inter']);
        $this->assertTrue($v->fails());
    }

    // ── regex ─────────────────────────────────────────────────────────────────

    public function test_regex_passes_valid_gstin(): void
    {
        $v = Validator::make(
            ['gstin' => '22AAAAA0000A1Z5'],
            ['gstin' => 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/']
        );
        $this->assertFalse($v->fails());
    }

    public function test_regex_fails_invalid_gstin(): void
    {
        $v = Validator::make(
            ['gstin' => 'INVALID'],
            ['gstin' => 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/']
        );
        $this->assertTrue($v->fails());
    }

    // ── confirmed ─────────────────────────────────────────────────────────────

    public function test_confirmed_passes_matching(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        );
        $this->assertFalse($v->fails());
    }

    public function test_confirmed_fails_mismatch(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'other'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->fails());
    }

    // ── nullable ──────────────────────────────────────────────────────────────

    public function test_nullable_always_passes(): void
    {
        $v = Validator::make(['cin' => null], ['cin' => 'nullable']);
        $this->assertFalse($v->fails());
    }

    // ── custom messages ───────────────────────────────────────────────────────

    public function test_custom_message_per_field_and_rule(): void
    {
        $v = Validator::make(
            ['name' => ''],
            ['name' => 'required'],
            ['name.required' => 'Business name is required.']
        );
        $this->assertEquals('Business name is required.', $v->firstError());
    }

    public function test_custom_message_per_field(): void
    {
        $v = Validator::make(
            ['name' => ''],
            ['name' => 'required'],
            ['name' => 'Please provide a name.']
        );
        $this->assertEquals('Please provide a name.', $v->firstError());
    }

    // ── one error per field ───────────────────────────────────────────────────

    public function test_only_first_rule_error_per_field(): void
    {
        $v = Validator::make(
            ['age' => 'abc'],
            ['age' => 'required|integer|min:18']
        );
        $this->assertTrue($v->fails());
        $this->assertCount(1, $v->errors());
        $this->assertStringContainsString('integer', $v->firstError());
    }

    // ── pipe and array rule formats ───────────────────────────────────────────

    public function test_pipe_separated_rules(): void
    {
        $v = Validator::make(['email' => 'bad'], ['email' => 'required|email']);
        $this->assertTrue($v->fails());
    }

    public function test_array_rules(): void
    {
        $v = Validator::make(['email' => ''], ['email' => ['required', 'email']]);
        $this->assertTrue($v->fails());
    }

    // ── multiple fields ───────────────────────────────────────────────────────

    public function test_multiple_fields_collect_all_errors(): void
    {
        $v = Validator::make(
            [],
            ['name' => 'required', 'email' => 'required', 'amount' => 'required']
        );
        $this->assertCount(3, $v->errors());
    }
}
