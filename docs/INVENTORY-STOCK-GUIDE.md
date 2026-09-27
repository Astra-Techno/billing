# Stock guide for Indian shops

Open **Stock** from the main menu. Existing businesses start in **Billing only**, so their current billing continues without stock restrictions.

## Choose a stock mode

- **Billing only:** stock is not reduced or checked. Use this for service businesses or shops maintaining stock elsewhere.
- **Show stock:** the app maintains stock and warns about shortages, while the cashier can continue billing.
- **Control stock:** the app prevents finalising a bill above the available quantity.

## Start using stock

1. Open a physical product and enable **Maintain stock for this item**.
2. Enter its purchase price, MRP, low-stock alert and optional barcode.
3. Open **Stock > Stock In / Correction** and add Opening Stock.
4. When an invoice is marked Sent or Paid, stock reduces from its selected shop. Cancelling it restores stock. A return credit note restores the returned quantity.
5. Receiving a purchase order adds stock. Purchase unit conversion supports cases such as one box containing twelve pieces.

## Shops and godowns

Every business receives **Main Shop** automatically. Single-shop users can ignore locations. Multi-location businesses can add a branch, godown or damaged/returns store. A transfer moves through **Draft > In transit > Received**, preventing the destination from counting goods before receipt.

## Safe corrections

Use Purchase Stock In for incoming goods, Purchase Return for goods sent back to a supplier, Damaged / Expired for loss, and Physical Stock Count to set the counted balance. Stock History keeps the user, date, reference, quantity and resulting balance. Posted movements are reversed rather than silently deleted.

Advanced trade fields support batch/lot, expiry and serial/IMEI details. They stay hidden unless enabled in Stock Settings. Tooltips beside unfamiliar controls explain what they do.

The cloud edition is the authority when several shops bill at the same time. The offline desktop edition can maintain several logical locations from one PC, but independent disconnected branch PCs cannot safely share the last available stock.
