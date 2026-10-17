# AI Billing Multi-Counter Shop Setup

This guide is for Indian supermarkets and shops using one Main PC and up to five billing Counter PCs on the same router.

## Before installation

- Choose the most reliable computer as the **Main PC**. It stores all products, stock, bills and backups.
- Connect every PC to the same private Wi-Fi or LAN router.
- Keep the Main PC and router on a UPS where possible.
- Set the Windows network profile to **Private** on every PC.
- Connect each thermal printer to its own Counter PC.

## Install the Main PC

1. Run the AI Billing installer and choose **Main PC**.
2. Complete registration, activation, business setup, GST settings and product import.
3. Open **Settings → Billing Counters**.
4. Add Counter 1, Counter 2 and the required shop location.
5. Leave AI Billing running. The Main PC service starts automatically with Windows.

## Connect a Counter PC

1. On the Main PC, choose **Connect a counter** and note the six-digit code.
2. Install AI Billing on the cashier computer and choose **Billing Counter**.
3. Counter Setup searches the shop network automatically. Confirm the displayed Main PC.
4. Enter the six-digit code.
5. On the Main PC, assign the request to the correct counter and approve it.
6. Open **AI Billing Counter** and sign in with the cashier account.

Never share the pairing code outside the shop. It expires after ten minutes.

## Start daily billing

1. Start the router and Main PC first.
2. Open AI Billing Counter on each cashier PC.
3. Enter the cash already present in the drawer and open the cashier shift.
4. Create one small test bill and print it before opening the counter to customers.
5. Products, prices and stock are shared immediately through the Main PC.

## Close a cashier shift

1. Select **Close shift** in POS.
2. Count the physical cash in the drawer.
3. Enter counted cash and add a note for any difference.
4. The app compares opening cash plus cash sales against counted cash. UPI and card totals are shown separately.
5. Owners can review every shift under **Settings → Billing Counters**.

## Backups

- AI Billing creates a daily local backup.
- At least once a day, open **Backup & Restore**, download a backup and copy it to a labelled USB drive.
- Test restoring a recent backup before relying on it.
- Keep backup USB drives secure because they contain customer and billing data.

## Replace a failed Main PC

1. Install AI Billing on the replacement computer and choose **Main PC**.
2. Activate it and restore the newest `.aibackup` file.
3. Select **This is a replacement Main PC** during restore.
4. Create a new pairing code under Billing Counters.
5. Run **AI Billing Counter Setup** on every counter and approve each device again.
6. Verify a bill, stock quantity, GST calculation and printer before normal billing.

## If a Counter says Main PC not connected

1. Check that the router and Main PC are switched on.
2. Confirm both PCs use the same router and Windows shows a Private network.
3. Wait 30 seconds and reopen AI Billing Counter; it retries while the Main PC starts.
4. If the Main PC was replaced or renamed, run **AI Billing Counter Setup** again.
5. Do not install a second Main PC with separate data and continue billing; this will split stock and invoice records.

## Release acceptance checklist

- Bill simultaneously from at least two Counter PCs.
- Sell the last available unit from two counters and confirm strict stock prevents overselling.
- Verify invoice numbers never duplicate.
- Restart the router and reconnect counters.
- Restart the Main PC and confirm its service starts automatically.
- Close shifts and compare cash, UPI and card totals.
- Create, download and restore a backup on a test Main PC.
- Test the customer's actual barcode scanners and 58mm/80mm printers.
