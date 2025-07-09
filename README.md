# Mautic Connector
## Very Simple Mautic to WooCommerce connector

Simple WooCommerce to Mautic integration plugin that transfers comprehensive data between the two platforms. Here's what the plugin does:
Key Features:
Data Transfer:

Customer Information: Name, email, phone, billing address, company
Order Details: Order ID, status, total, currency, payment method, shipping method
Product Data: Items purchased, quantities, prices, SKUs
Customer Behavior: Registration dates, order history, customer notes

Automatic Sync Triggers:

New customer registration
Order completion
User registration

Manual Sync Options:

Bulk sync all customers
Bulk sync all completed orders

Installation & Setup:

Download zip and install it to your WordPress installation
Activate the plugin in WordPress admin
Go to Settings > WC Mautic to configure:

Mautic URL (e.g., https://your-mautic-instance.com)
Mautic username
Mautic password



What Data Gets Transferred:
Contact Data:

Personal info (name, email, phone)
Billing address details
Company information
Customer ID and registration date
Tags for segmentation

Order Data:

Order details (ID, number, status, total)
Payment and shipping methods
Individual product items with quantities and prices
Customer notes
Order completion events

The plugin uses Mautic's REST API with OAuth authentication and includes error handling. It also creates custom events in Mautic for order completions, making it easy to set up automated campaigns based on purchase behavior.
Would you like me to add any specific features or modify the data mapping?
