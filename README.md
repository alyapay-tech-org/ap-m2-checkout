# AlyaPay Magento 2 Payment Module

AlyaPay online checkout integration for Magento 2.

## Installation

### Option 1: Copy to app/code

1. Copy the `AlyaPay/Payment` folder to your Magento `app/code/AlyaPay/` directory.

2. Enable the module:
   ```bash
   php bin/magento module:enable AlyaPay_Payment
   php bin/magento setup:upgrade
   php bin/magento cache:flush
   ```

### Option 2: Composer

```bash
composer require alyapay/magento2-payment
php bin/magento module:enable AlyaPay_Payment
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Configuration

1. Go to **Stores > Configuration > Sales > Payment Methods**
2. Open the **AlyaPay** section
3. Configure:
   - **Enabled**: Yes
   - **Title**: Payment method title (e.g. "AlyaPay")
   - **API Base URL**: Use default or override (Sandbox: `https://sandbox-api.alyapay.com`, Production: `https://api.alyapay.com`)
   - **API Key**: Your AlyaPay API key (provided when you register your webhook URL)
   - **Webhook URL**: Copy this URL and provide it to AlyaPay when requesting your API key

## Return URLs

When configuring with AlyaPay, provide these return URLs:

- **Success**: `https://your-store.com/alyapay/result/success`
- **Cancel**: `https://your-store.com/alyapay/result/cancel`
- **Failure**: `https://your-store.com/alyapay/result/failure`

## Flow

1. Customer selects AlyaPay at checkout and places order
2. Magento calls `POST /session-intents` (1-step flow) to get checkout URL
3. Magento redirects customer to AlyaPay checkout (with `redirect_url` param)
4. Customer pays on AlyaPay (AlyaPay frontend handles checkout API)
5. AlyaPay redirects to `redirect_url` with `status` and `transaction_id` query params
6. Magento verifies transaction status and updates order (SUCCESS) or cancels (FAILURE)
7. Webhooks handle cancelled/expired transactions asynchronously

## Webhook

The webhook endpoint is: `https://your-store.com/alyapay/result/webhook`

Provide this URL to AlyaPay when requesting your API key. The webhook handles:
- `transaction.cancelled`
- `transaction.expired`

## Support

See `ONLINE_CHECKOUT.md` in the project root for the full API reference.
