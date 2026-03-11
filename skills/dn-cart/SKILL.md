---
name: dn-cart
description: View the WordPress.com shopping cart using the dn CLI. Use when the user wants to see what domains are in their cart before checkout. User mode only - not available in partner mode.
---

# dn cart

View the contents of your WordPress.com shopping cart.

## Usage

```bash
dn cart
```

## Output

Table columns: Domain, Product, Cost

Shows "Your cart is empty." if no items.

## Notes

- **User mode only** - returns error in partner mode
- Reads from WPCOM `rest/v1.1/me/shopping-cart/no-site`
- Domains are added to cart via `dn register`
- Use `dn checkout` to complete the purchase
