<?php

namespace Tests\Feature;

use App\Http\Requests\StoreEmployeeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreEmployeeRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validEmployeeData(): array
    {
        return [
            'full_name' => 'Asha Sharma',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'password' => 'secret1',
            'confirm_password' => 'secret1',
            'role' => 'member',
            'department' => 'Engineering',
            'designation' => 'Civil Engineer',
            'alternate_number' => '8765432109',
            'aadhaar_number' => '234567890123',
            'pan_number' => 'ABCDE1234F',
            'status' => '1',
        ];
    }

    private function validatorFor(array $data)
    {
        $request = StoreEmployeeRequest::create('/super/employees/store', 'POST', $data);
        $request->setContainer($this->app);

        return Validator::make($data, $request->rules());
    }

    public function test_valid_employee_details_pass_validation(): void
    {
        $validator = $this->validatorFor($this->validEmployeeData());

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_invalid_employee_field_formats_are_rejected(): void
    {
        $cases = [
            'phone' => '1234567890',
            'alternate_number' => '987654321',
            'aadhaar_number' => '123456789012',
            'pan_number' => 'ABCDE12345',
            'email' => 'not-an-email',
            'full_name' => 'Asha123',
            'designation' => 'Driver',
            'confirm_password' => 'different',
        ];

        foreach ($cases as $field => $invalidValue) {
            $data = array_merge($this->validEmployeeData(), [$field => $invalidValue]);
            $validator = $this->validatorFor($data);

            $errorField = $field === 'confirm_password' ? 'password' : $field;
            $this->assertTrue(
                $validator->errors()->has($errorField),
                "Expected {$field} to fail validation."
            );
        }
    }

    public function test_lowercase_pan_is_normalized_before_validation(): void
    {
        $request = StoreEmployeeRequest::create(
            '/super/employees/store',
            'POST',
            array_merge($this->validEmployeeData(), ['pan_number' => 'abcde1234f'])
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        $this->assertSame('ABCDE1234F', $request->validated()['pan_number']);
    }
}
