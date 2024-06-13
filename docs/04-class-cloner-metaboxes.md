# class-cloner-metaboxes.php

This class extends the main `Cloner` class and is responsible for setting up the metaboxes which enable the linking and copying of posts to other subsites.

The class contains 4 private properties which contain network options, these are used as helpers throughout the class so there's no need to re-call the options from the database:

* $related_sites
* $main_site
* $enabled_cpts
* $current_site

## Functions:

**initialise_meta_box**

This public function is a callback for the `admin_init` action hook, it adds the network options to class properties and ensures metaboxes and required scripts are added to enabled sites. It contains two further action hooks: `admin_enqueue_scripts` and `add_meta_boxes`.

---

**enqueue_scripts**

This public function is a callback for the `admin_enqueue_scripts` action hook and simply enqueues the required script for Ajax powered metabox functionality.

---

**process_cloner_actions**

This public function is a callback for the `wp_ajax_cty-cloner` and `wp_ajax_cty-cloner-search` action hook callbacks. The function contains a security nonce verify function, and processes the `$_REQUEST` array sent via JavaScript. Depending on the data and action sent via JS it calls on other class methods and sends data back to the JS script using the `echo wp_json_encode( $response );` function.

The two actions processed are `cty-cloner` and `cty-cloner-search`.

<u>`cty-cloner` action:</u>

This is the main metabox action, on Update/Publish the script gathers user choices for each metabox (as well as other data such as source and target post/blog IDs), loops through each metabox and executes the desired function using the `do_metabox_action` function which is covered below.

<u>`cty-cloner-search` action:</u>

This action triggers the post search functionality which allows a user to find and link to another post from the choses subsite, it uses the `search_posts` function which is also covered below.

---

**add_cloner_meta_box**

This public function is a callback for the `add_meta_boxes` action hook, the function loops through enabled sites and post types and ensure a metabox is added where appropriate using the [add_meta_box](https://developer.wordpress.org/reference/functions/add_meta_box/) function. There is another callback to `cloner_meta_box_cb` which is covered below.

**cloner_meta_box_cb**

This is a public callback function used in the above `add_cloner_meta_box` function, it's used to render the HTML for each metabox. This function instantiates a new object for each subsite called `Cloner_Relationship`, more info here: [06-class-cloner-relationship.txt](./06-class-cloner-relationship.txt).

The function sets up source/target variables used to determine which options are displayed, i.e.

```php
$args     = [
	'source-post' => $post_id,
	'source-blog' => $current_site,
	'target-blog' => $target_blog,
	'target-post' => false,
];
```

The Relationship subclass is used to  determine if a pre-existing relationship exists for the current post and adds it to arguments if so, i.e. `$args['target-post'] = $relationship[ $key ][ $rel_args['target-blog'] ];`.

Once the variables are gathered a helper function `render_meta_box` is used to render the HTML, for info see below: `$this->render_meta_box( $args );`

---

**render_meta_box**

This private function is a helper to render HTML for each metabox. It sets up the input fields and data attributes which are then gathered and processed by the JS script. This function is also used by the `do_metabox_action` function to re-render the HTML after JS scripts are done processing data.

Parameters:

```php
/**
 * @param array $args source/target args for a relationship.
 * @param bool  $echo whether to echo or return markup.
 */
```

Usage:

```php
$args = [
	'source-post' => $post_id,
	'source-blog' => $current_site,
	'target-blog' => $target_blog,
	'target-post' => $target_post,
];
$this->render_meta_box( $args );
```

---

**search_posts**

This private helper function is used to search a target subsite for a specific post in order to be able to relate it to the source post. It takes a single parameter which is associative array containing a `targetBlog` and `searchTerm` variables, both of which are required.

The function switches to the target site and runs a simple `WP_Query` loop and returns the top 20 posts found for the search term provided wrapped in simple HTML markup. HTML is rendered for each post using the `render_post_item` function documented below.

---

**do_metabox_action**

This is a private helper function which performs various actions depending on the choice made in the metabox. The function instantiates two sub-classes to do this: [Cloner_Relationship](./06-class-cloner-relationship.md) and [Cloner_Copier](./07-class-cloner-copier.md).

The function has 1 required parameter which looks something like this:

```php
$args = [
	'source-post' => $post_id,
	'source-blog' => $current_site,
	'target-blog' => $target_blog,
	'target-post' => $target_post ?? false,
];
```

<u>copy</u> and <u>overwrite</u> actions

These actions are almost identical, the only difference is that for `overwrite` the `target-post` array property is already set. The `Cloner_Copier` class is called to copy post contents and meta to the target site, in the case of the `copy` action the class method `copy_post` returns a target post ID. More on how this works is detailed here: [Cloner_Copier](./07-class-cloner-copier.md).

The `Cloner_Relationship` class is then called to update the relationship array stored in our custom database table. More on how this works is detailed here: [Cloner_Relationship](./06-class-cloner-relationship.md).

<u>link</u>

This action simply takes the updated `target-post` variable from the metabox and updates the relationship array using the `Cloner_Relationship` class.

<u>unlink</u>

This action removes the target post received in the `target-post` array variable from the custom relationship database table.

Once the metabox variables are updated the HTML is re-rendered using the above `render_meta_box` function, the resulting HTML is returned for processing via Ajax.

---

**render_post_item**

This is a private helper function which renders a post item title and ID, as well as the target blog ID in some basic label/input HTML tags. The function is only referenced once in `search_posts`.