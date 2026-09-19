# Tax Saathi Ultimate – Core PHP + MariaDB

Tax Saathi Ultimate is a dynamic income tax service website + CRM built with **Core PHP MVC style + MariaDB**.

## Included modules

- Public website inspired by a premium tax-service layout
- Dynamic services and filing fees
- Dynamic service-wise required documents
- **WhatsApp OTP login/register**
- Client portal
- Manual payment proof upload
- Admin approval gate
- Client uploaded documents remain locked until admin approval
- Work in progress / completed statuses
- Admin uploads multiple completed files back to the same order ID
- Client downloads delivered files order-wise
- Invoice and manual payment tracking
- Staff roles and permissions
- Dynamic tax calculators from admin tax rules
- Notification templates, logs, and WhatsApp OTP settings
- Website content management from admin
- Reports and activity timeline

## WhatsApp OTP defaults prefilled

These values are already seeded in `website_settings`:

- Sender number: `+917005727288`
- Template name: `ahibi_otp`
- Template ID: `925465936518158`
- Language: `en`
- Message ID: `14450`

You still need to set:

- `whatsapp_api_url`
- `whatsapp_api_token`

The app sends a generic JSON payload to the configured API URL. If no API URL is set, the app logs the OTP locally and, in debug mode, shows the development OTP in the flash message.

## Default demo accounts

Admin:
- Phone: `+917005727288`

Manager:
- Phone: `+919000000011`

Executive:
- Phone: `+919000000021`

Client:
- Phone: `+919000000001`

All of the above use **WhatsApp OTP**, not passwords.

## Setup

1. Create a database, for example `tax_saathi`
2. Import `database.sql`
3. Update `app/config/config.php`
4. Point your document root to the `/public` directory
5. Ensure `storage/uploads/documents` is writable

## Notes

- This is a strong starter build, not a hosted SaaS deployment.
- Add your own production mail/WhatsApp gateway credentials before going live.
- Review and harden file-upload validation, rate-limiting, and server rules for production.
