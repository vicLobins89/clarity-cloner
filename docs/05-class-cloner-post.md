# class-cloner-post.php

This class extends the main `Cloner` class and contains functions relating to the [WP_Post](https://developer.wordpress.org/reference/classes/wp_post/) object.

## Functions:

**unlink_deleted**

The public function is a callback for the [before_delete_post](https://developer.wordpress.org/reference/hooks/before_delete_post/) action hook. The function simply takes the deleted post ID and ensures the post is removed from our custom relatioships array using the [Cloner_Relationship](./06-class-cloner-relationship.md) class.

---

**edit_canonical_url**

The public function is a callback for the [get_canonical_url](https://developer.wordpress.org/reference/hooks/get_canonical_url/) and [wpseo_canonical](https://developer.yoast.com/features/seo-tags/canonical-urls/api/) filter hooks. The function ensures that if we're looking at a cloned post the correct canonical tag is set, referring the post back to the Main Site (which is set in Network Settings).