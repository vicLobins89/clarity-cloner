# class-clarity-cloner.php

This is a base abstract class which all other classes extend and inherit from - this class never gets instantiated on its own. Some child classes call the construct of this base class in order to access the `$table_name` property and in order to display WordPress notices.

## Functions:

**display_notice**

This public function is hooked into `admin_notices` and `network_admin_notices` action hooks, in order to use them the child sub-classes have to either call the `parent::_construct()` method or define their own action hooks. Then all that needs doing is updating the class property `notices`, like so:

```php
$this->notices = [
	'error' => [
		'content'        => __( 'You do not have sufficient priveleges to use this plugin!', 'cty' ),
		'is-dismissable' => false,
	],
];
```

`notices` is an associative array allowing the display of multiple notices at the same time, it is keyed by the type (or colour) of the notice:

* error = red
* warning = yellow
* success = green
* info = blue

For more info see: https://developer.wordpress.org/reference/hooks/admin_notices/

---

**verify_nonce**

This is a protected function which the child sub-classes can use to verify a nonce in a GET/POST request.

Usage:

```php
$this->verify_nonce()
```

There is also a defined class property called `nonce_action`, in order to verify correctly ensure the nonce value is created using this property as the action, e.g.:

```php
wp_create_nonce( $this->nonce_action );
```

---

**get_blog_post**

This is a public function which retrives a `WP_Post` object from a given site. Both function parameters are required. 

Usage:

```php
$blog_post = $this->get_blog_post( $post_id, $blog_id );
```

There are two additional class properties added which are not in a typical `WP_Post` object, these are:

```php
$blog_post->permalink
$blog_post->post_edit_link
```

---

**debug**

Public plugin debugger.

Parameters:

```php
/**
 * @param mixed  $data any data we want to debug.
 * @param string $type 'display' or 'log' the results, 'display' is default.
 * @param bool   $exit do we want to exit the function.
 */
```

Usage:

```php
$this->debug( $data, 'log' );
```