# Block Audit Command

This is a WP-CLI package for auditing block usage in post content, which can help decide which blocks to prioritize before a content migration or redesign.

Quick links: [Using](#using) | [Installing](#installing) | [About](#about)

## Using

This package implements the following commands:

### wp block-audit run

Report how many of each type of block there is in post content and aggregated details about them.

The report includes the number of times each block is used, the post types it's used in, and details comprising:

- For all blocks, the count of 'align' attribute values.
- For 'core/heading' blocks, the count of each heading level used.
- For 'core/embed' blocks, the count of each embed provider used.

You can add more details to the report with the `alley_block_audit_block_type_details` and `alley_block_audit_{$block_name}_block_type_details` filters:

```php
add_filter(
	'alley_block_audit_block_type_details',
	function ( $details, $block_name, $attrs, $inner_html, $block ) {
		if ( isset( $attrs['fontSize'] ) && is_string( $attrs['fontSize'] ) ) {
			$details['fontSize'][ $attrs['fontSize'] ] ??= 0;
			$details['fontSize'][ $attrs['fontSize'] ]++;
		}

		return $details;
	},
	10,
	5,
);

add_filter(
	'alley_block_audit_core/image_block_type_details',
	function ( $details, $block_name, $attrs, $inner_html, $block ) {
		if ( isset( $attrs['aspectRatio'] ) && is_string( $attrs['aspectRatio'] ) ) {
			$details['aspectRatio'][ $attrs['aspectRatio'] ] ??= 0;
			$details['aspectRatio'][ $attrs['aspectRatio'] ]++;
		}

		return $details;
	},
	10,
	5,
);
```

~~~
wp block-audit run  [--<field>=<value>] [--format=<format>] [--verbose] [--rewind]
~~~

**OPTIONS**

    [--<field>=<value>]
        One or more args to pass to WP_Query except for 'order', 'orderby', or 'paged'.

    [--orderby=<column>]
        Set the order of the results.
        ---
        default: name
        options:
          - name
          - count
        ---

    [--format=<format>]
        Render output in a particular format.
        ---
        default: table
        options:
        - table
        - csv
        - json
        - count
        - yaml
        ---

    [--verbose]
        Turn on verbose mode.

    [--rewind]
        Resets the cursor so the next time the command is run it will start from the beginning.

**EXAMPLES**

    $ wp block-audit run --post_type=post,page
    +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
    | Block Name                        | Count | Example URL                                                | Post Types      | Details                                                              |
    +-----------------------------------+-------+------------------------------------------------------------+-----------------+----------------------------------------------------------------------+
    | core/archives                     | 3     | https://www.example.com/2023/01/13/widgets-block-category/ | ["post"]        |                                                                      |
    | core/button                       | 12    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        | {"align":{"left":2,"center":1,"right":1}}                            |
    | core/code                         | 2     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
    | core/column                       | 40    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
    | core/columns                      | 13    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        | {"align":{"wide":2,"full":1}}                                        |
    | core/cover                        | 21    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"left":1,"center":2,"full":1,"wide":2}}                    |
    | core/file                         | 3     | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        |                                                                      |
    | core/gallery                      | 10    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        |                                                                      |
    | core/group                        | 25    | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
    | core/heading                      | 23    | https://www.example.com/2023/01/13/text-category-blocks/   | ["post","page"] | {"H1":2,"H2":11,"H3":4,"H4":2,"H5":2,"H6":2}                         |
    | core/html                         | 2     | https://www.example.com/2023/01/13/widgets-block-category/ | ["post"]        |                                                                      |
    | core/image                        | 19    | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"center":2,"left":2,"right":3,"none":1,"wide":1,"full":1}} |
    | core/list                         | 9     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
    | core/list-item                    | 6     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
    | core/media-text                   | 6     | https://www.example.com/2023/01/13/media-category-blocks/  | ["post"]        | {"align":{"full":1}}                                                 |
    | core/paragraph                    | 262   | https://www.example.com/2023/01/13/text-category-blocks/   | ["post","page"] | {"align":{"center":16,"right":1,"left":1}}                           |
    | core/pullquote                    | 4     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
    | core/spacer                       | 4     | https://www.example.com/2023/01/13/design-category-blocks/ | ["post"]        |                                                                      |
    | core/table                        | 4     | https://www.example.com/2023/01/13/text-category-blocks/   | ["post"]        |                                                                      |
    +-----------------------------------+-------+---------------------------------------------------------------+-----------------+----------------------------------------------------------------------+


## Installing

Installing this package requires WP-CLI v1.3.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install alleyinteractive/wp-block-audit-command:@stable
```

You can also install this package with Composer:

```bash
composer require alleyinteractive/wp-block-audit-command
```

## About

### License

[GPL-2.0-or-later](https://github.com/alleyinteractive/wp-type-extensions/blob/main/LICENSE)

### Maintainers

[Alley Interactive](https://github.com/alleyinteractive)
