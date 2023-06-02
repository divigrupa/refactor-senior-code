<?php

namespace Tests\Feature;

use App\Traits\GoogleApiTrait;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleApiTest extends TestCase
{
    use GoogleApiTrait;


    public function test_CheckIfCorrectResultIsReturned(): void
    {
        $distance = fake()->randomNumber();
        $duration = fake()->randomNumber();
        Http::fake(['https://maps.googleapis.com/maps/api/distancematrix/*' => Http::response(
            [
                'destination_addresses' => ['UK'],
                'rows' => [
                    [
                        'elements' => [[
                            'distance' => ['value' => $distance],
                            'duration' => ['value' => $duration],
                        ]],
                    ],
                ],
            ],
        )]);

        $this->assertEquals(
            (object) ['distance' => $distance, 'duration' => $duration],
            $this->getPostalCodeDetail('')
        );
    }

    public function test_CheckIfCorrectRequestIsSentToGoogle(): void
    {
        $destinations = fake()->word();
        Http::fake();
        try {
            $this->getPostalCodeDetail($destinations);
        } catch (\Throwable) {
        }

        Http::assertSent(fn(Request $request) => $request->url() === 'https://maps.googleapis.com/maps/api/distancematrix/json?destinations=' . urlencode($destinations) . '&origins=CM26PJ&units=metric&key=xxxddd');
    }

    public function test_GoogleApiFailThrowsError(): void
    {
        Http::fake(['https://maps.googleapis.com/maps/api/distancematrix/*' => Http::response(null, 500)]);
        $this->assertThrows(
            fn() => $this->getPostalCodeDetail(''),
            Exception::class,
            'Error while getting data from google api'
        );
    }

    /**
     * @dataProvider googleApiInvalidResponses
     */
    public function test_InvalidResponseThrowsPostalCodeInvalidException(array $content): void
    {
        Http::fake(['https://maps.googleapis.com/maps/api/distancematrix/*' => Http::response($content)]);

        $this->assertThrows(
            fn() => $this->getPostalCodeDetail(''),
            Exception::class,
            'postal code not valid'
        );
    }

    private function googleApiInvalidResponses(): array
    {
        return [
            // Case
            [
                []
            ],
            // Case
            [
                ['rows' => []]
            ],
            // Case
            [
                ['rows' => [['' => []]]]
            ],
            // Case
            [
                ['rows' => [['elements' => []]], 'destination_addresses' => ['US']]
            ]
        ];
    }
}
