<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Markets\MarketProfileService;
use InvalidArgumentException;

class MarketProfileController extends Controller
{
    public function __construct(private MarketProfileService $markets)
    {
    }

    public function index()
    {
        $profiles = [];
        foreach ($this->markets->all() as $code => $profile) {
            $profiles[] = $this->markets->publicProfile($code);
        }

        return response()->json(['data' => $profiles], 200);
    }

    public function show(string $country)
    {
        try {
            return response()->json(['data' => $this->markets->publicProfile($country)], 200);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['errors' => [['code' => 'unsupported_country', 'message' => $exception->getMessage()]]], 404);
        }
    }
}
