# class-cloner-setup.php

This class extends the main `Cloner` class and is responsible for setting up the custom database table and the network settings screens, as well as saving the options for the network.

## Functions:

**activate_plugin**

This public function is fired on the `register_activation_hook` hook as soon as the plugin is network enabled. The function checks if current user is supoer admin and proceeds to create a table using [maybe_create_table](https://developer.wordpress.org/reference/functions/maybe_create_table/).

---

**setup_admin_settings**

This public function is a callback to the `network_admin_menu` action hook, it uses the [WordPress Settings API](https://developer.wordpress.org/plugins/settings/settings-api/) to register and set up options pages.

The function also has a secondary callback function which is `cty_page_cb`, this gets the saved site options using `$options = get_site_option( 'cty_cloner_options' );` and renders all the HTML required for the options form.

---

**save_admin_settings**

This public function is a callback for the `network_admin_edit_cty-save` action hook, it processes the variables sent in the `$_POST` array and saves them to site options, it then redirects back to the settings page.

---

**get_all_cpts**

This private function retrieves and merges all the custom post types (and Post and Page) from all the enabled subsites.

Usage:

```php
$sites    = get_sites( [ 'fields' => 'ids' ] );
$all_cpts = $this->get_all_cpts( $sites );
```