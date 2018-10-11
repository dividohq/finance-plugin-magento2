# Divido Magento 2 Extension

The Divido Extension for Magento 2 enables you to add Divido financing to you checkout payment options.

## Divido subscription
Visit https://www.divido.com/sign-up/ to sign up for a demo account.

##Installation
### Install with composer

```
$ cd /path/to/magento
$ composer require divido/divido-magento2
$ php bin/magento setup:upgrade
```

## Setup
After getting a Divido subscription, only two settings need to be made, in order to enable the Divido option at checkout.

In `Stores > Configuration > Sales > Payment Methods` you will find the **Divido Financing** option.
  
Enter the API-key from your Divido account in the field **API-key**  
Set the field **Enabled** to **Yes**

That should be it to get going with Divido as a checkout option.

### Setup fields description

| Field | Description |
| --- | --- |
| API-key | Obtained from Divido, needed to identify your shop in communications with the Divido system |
| Shared secret | Obtained from Divido, enables message signing. |
| Enabled | Enables / Disables both the product page calculator and checkout option |
| Debug | Logs useful information when troubleshooting |
| Title | The title of the checkout option |
| Create order on | Decide at what stage in the Divido process you want to create the order and reserve stock |
| New order status | What status a new order created through Divido will have |
| Automatic fulfilment | Notify Divido and the lender that an order has been fulfilled |
| Fulfilment status | Set the status at which an order is considered fulfilled |
| Minimum cart amount | Under this amount, Divido is not available as a checkout option |
| Product selection | Decide what products are available on finance |
| Displayed plans | Decide what plans are globally available |

## Content Page Widgets
The Divido Extension also includes two widgets which can be inserted into the custom pages on your site. These widgets
allow you to show the various options available to potential customers by either setting a static amount in the plugin's
configuration, or by showing a text box that would allow the customer to enter an amount and instantly receive the details of their
payment plan on that basis.

The steps to implement this feature are explained below:

1. Click on `Content` and then `Pages` in the submenu in your administration panel.
2. Click on `Select` and then `Edit` in the submenu in the row corresponding to the page you wish to add the widget, or alternatively
click the `Add New Page` button if you'd like the widget to feature on a new page.
3. Click on the down arrow adjacent to the `Content` header to expand that section, then place the cursor where you would like the 
widget to appear in the text editor and select the `Insert Widget` icon at the top left area of the editor.
4. Select either *Divido Block Widget* or *Divido Popup Widget* from the `Widget Type` selection list
5. Enter a default amount that the widget will calculate within the `Default Amount` widget option
6. Choose to either show a text box, allowing customers to enter an amount which will dynamically calculate their potential plan, or
hide this option by selecting No in the `Show text box?` widget option
7. Click on the Insert Widget button at the bottom right
8. Click on the `Save Page` button on the top right.

Providing you have an API key inserted in your Setup (see above) the widget should now be displaying in the page you have edited/created.

Please note that the Divido Widgets will stretch to 100% of the width of its container, so you may wish to enclose the widget within a
`table column` or `div` if you would like to inhibit the width.