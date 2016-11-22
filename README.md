# Drupal Commerce Fuzzy.ai Example

This Drupal module is an example integration to create Drupal Commerce product recommendations using an agent from https://fuzzy.ai/ .

## Public Demo

You can see a demonstration of this module in action here: http://dev-drupal-commerce-fuzzy.pantheonsite.io/

Click on "Products" to browse available products. Clicking on any Product will show full product details and a "Recommended Products" block on the side. The recommendations are calculated using the included Fuzzy Agent.

## Installation

These instructions assume that you have a working Drupal 8.2.x installation with Drupal Commerce enabled and configured.

1. Register for an account at https://fuzzy.ai
1. Install the fuzzy.ai command line tool: `npm install -g fuzzy.ai-cmdln`
1. Add the "Product Affinity" agent using the included .cson file from this repository: `fuzzy.ai -k YOUR_API_KEY create product-affinity.cson`
1. Copy this module to your modules folder (e.g. `modules/custom/commerce_fuzzy`)
1. Add the Fuzzy.ai SDK via composer (`composer install fuzzy-ai/sdk`)
1. Enable the "Commerce Fuzzy Example" module at `admin/modules`.
1. Enable the "Recommended Products" block.
  * Enter your API Key from https://fuzzy.ai/
  * Enter the Agent ID (from the agent you created previously).

Optionally, the agent will use Categories if you have a Taxonomy Term reference on your products as "field_category".
