<?php

namespace Marvel\Traits;

use Marvel\Database\Models\Settings;
use Marvel\Database\Models\Wallet;

trait Wallets
{

  /**
   * Convert currency to wallet points
   */
  private function currencyToWalletPoints($currency)
  {
    $currencyToWalletRatio = $this->currencyToWalletRatio();

    $points = $currency * $currencyToWalletRatio;

    return  intval($points);
  }

  /**
   * Converts wallet points to currency
   */
  public function walletPointsToCurrency($points)
  {
    $currencyToWalletRatio = $this->currencyToWalletRatio();

    $currency = $points / $currencyToWalletRatio;

    return  round($currency, 2);
  }

  private function currencyToWalletRatio()
  {
    try {
      $settings = Settings::first();
      $currencyToWalletRatio = $settings['options']['currencyToWalletRatio'];
    } catch (\Throwable $th) {
      $currencyToWalletRatio = 1;
    }
    return $currencyToWalletRatio;
  }
  /**
   * Converts wallet points to currency
   */
  public function giveSignupPointsToCustomer($customer_id)
  {
    try {
      $settings = Settings::first();
      $signupPoints = $settings['options']['signupPoints'];
    } catch (\Throwable $th) {
      $signupPoints = 0;
    }

    $wallet = Wallet::firstOrCreate(['customer_id' => $customer_id]);
    $wallet->total_points = $wallet->total_points + $signupPoints;
    $wallet->available_points = $wallet->available_points + $signupPoints;
    $wallet->save();
  }
}
