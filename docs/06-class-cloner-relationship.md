# class-cloner-relationship.php

This class extends the main `Cloner` class and is responsible for creating and manipulating the relationships array stored in our custom database table.

It contains 4 private properties for source and target post/blog variables which are:

* $source_post
* $source_blog
* $target_blog
* $target_post

## Functions:

**update_props**

This public function updates the class properties with a given data array.

Usage:

```php
$args = [
	'source-post' => $post_id,
	'source-blog' => $current_site,
	'target-blog' => $target_blog,
	'target-post' => $target_post ?? false,
];

// Inside current class.
$this->update_props( $args );

// Elsewhere.
$rel_obj = new Cloner_Relationship();
$rel_obj->update_props( $args );
```

---

**get_rid**

This is a private helper function which grts the relationship ID from the given post/blog vars. Two parameters are required, which are `$post_id` and `$blog_id`.

Usage:

```php
$rid = $this->get_rid( $source_post, $source_blog );
```

---

**get_relationship**

This public function gets the relationship array (and relationship ID) for a source post and blog ID. It has 2 optional parameters, which are `$source_post` and `$source_blog`. However it can also be used with the instantiated class properties: `$this->source_post` and `$this->source_blog`.

Usage:

```php
// Inside current class with provided parameters.
$rel_array = $this->get_relationship( $this->source_post, $this->source_blog );

// Elsewhere with the properties being set on class instantiation.
$args      = [
	'source-post' => $post_id,
	'source-blog' => $current_site,
	'target-blog' => $target_blog,
	'target-post' => $target_post ?? false,
];
$rel_obj   = new Cloner_Relationship( $args );
$rel_array = $rel_obj->get_relationship();

// Can also be done just by providing parameters.
$rel_obj   = new Cloner_Relationship();
$rel_array = $rel_obj->get_relationship( $post_id, $blog_id );

// Returned vars.
$rid          = $rel_array['rid'];
$relationship = $rel_array['relationship'];
```

---

**add_target_post**

This public function adds a target post to the relationship array, or creates a new relationship array if one is not found. It has no parameters but all 4 class properties are required in order to continue the function.

Usage:

```php
$metabox      = [
	'source-post' => $post_id,
	'source-blog' => $current_site,
	'target-blog' => $target_blog,
	'target-post' => $target_post ?? false,
];
$rel_obj = new Cloner_Relationship( $metabox );
$metabox['target-post'] = $copier->copy_post();
$rel_obj->update_props( $metabox );
$rel_obj->add_target_post();
```

In the above example the `Cloner_Relationship` class is instantiated with the properties gathered from our metabox values (this would be triggered for each metabox found on the post edit page). If the metabox action is overwrite, the `$metabox['target-post']` variable should already be there, if not we will set it with the `$metabox['target-post'] = $copier->copy_post();` function. We then update the `Cloner_Relationship` props to include the new `target-post` variable and run the `add_target_post` function to add it to the relationship array.

The function either MySQL INSERTS or UPDATES the relationship array in our custom database table, it also updates the source and target post meta fields with the relationship ID.

---

**remove_target_post**

This public function removes the target post from the relationship array in our custom database table. And removes th relationship ID from the post meta, this is only triggered when the `unlink` metabox action is run or when a post is permanently deleted.

Usage:

```php
$rel_args = [
	'target-post' => $post_id,
	'target-blog' => get_current_blog_id(),
];
$rel_obj = new Cloner_Relationship( $rel_args );
$rel_obj->remove_target_post();
```