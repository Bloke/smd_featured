# Changelog

## 0.60 2016-xx-yy

* Textpattern 4.6 compatible.

## 0.50 2012-10-26

* Textpattern 4.5 compatible.

## 0.41 2011-12-02

* Allowed `section_list` in the admin page's URL to limit the list of featured articles to the given section(s) (thanks mickmelon).
* Fixed trim on label attribute (thanks maniqui)

## 0.40 2011-05-12
* Added positional sorting.
* Allowed label/title/desc to be optional.
* Removed 'Are You Sure' message if label/title/desc not used.
* Added full Write tab edit links (all thanks pieman).
* Added hidden prefs to interface.
* Made textile prefs global (previous settings will be lost on upgrade).
* Global prefs only editable by publisher.
* `<txp:smd_featured />` default sort order now `feat_position asc, Posted desc`.

## 0.34 - 2011-01-17

* Added txp:else support to featured / unfeatured tags.

## 0.33 - 2011-01-17

* Added `history` attribute.
* Fixed smd_unfeatured to work correctly in individual article context after the v0.32 blooper (thanks johnstephens).
* Fixed smd_unfeatured bug to stop txp:article's default output if unfeatured list empty.

## 0.32 - 2010-11-01

* Added older/newer pagination support to `<txp:smd_unfeatured />` and permitted `section`, `status` and `time` filtering.
* Fixed some styling issues.

## 0.31 - 2010-10-11

* Better theme integration (thanks thebombsite).

## 0.30 - 2010-10-05

* Use `article_custom()` not `article()` to sidestep individual article context (thanks johnstephens).
* Pass `time` and `limit` to `article_custom()`.
* `limit` has default of 10.

## 0.22 - 2010-09-13

* Fixed missing doSlash() on untextiled html content (thanks bg).

## 0.21 - 2010-09-13

* Added 'title' and Textile options (thanks bg).

## 0.20 - 2010-09-07

* Renamed id column to feat_id.
* Fixed @label@ attribute bug (both thanks pieman).
* Label searches are now exact matches.
* Added prefs and pagination support for admin side.
* Added `<txp:smd_if_featured>`.
* Added label selection from dropdown during editing (thanks tye).

## 0.10 - 2010-06-18

* Initial release.
