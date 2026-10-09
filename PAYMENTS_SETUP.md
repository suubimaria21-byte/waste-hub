# Payment demonstration

The app demonstrates invoice creation and payment flow without charging anyone. A citizen can open an invoice, review the selected method and amount, then simulate a successful payment. The invoice becomes `Paid (demo)`, which is separate from the real `paid` status. No payment provider is contacted and no money or card/mobile-money credentials are handled.

## Demo behavior

- Rates are calculated server-side by the citizen billing category and submitted quantity.
- The invoice records the chosen method, payment timing, due date, and simulated reference.
- Demo references begin with `DEMO-`; only actual integration may set status to `paid`.

No gateway account, secret key, webhook, PHP cURL setup, or public URL is required for this demonstration. Existing databases receive the demo status and reference fields through the app's schema migration. New databases receive them from `database.sql`.
