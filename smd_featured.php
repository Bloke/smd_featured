<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_featured';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.60';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'http://stefdawson.com/';
$plugin['description'] = 'Quickly label and display featured articles for your home / landing pages';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '5';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '2';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

$plugin['textpack'] = <<<EOT
#@smd_feat
smd_feat_by_label => By label:
smd_feat_by_name => By name:
smd_feat_control_panel => Control panel
smd_feat_desc => Description
smd_feat_label => Label
smd_feat_lbl_edart => Edit full article in the Write panel
smd_feat_lbl_edfeat => Label and edit featured content
smd_feat_lbl_edpos => Set the article sort position
smd_feat_lbl_unfeature => Unfeature this article
smd_feat_manage_lbl => Click to select featured articles, which will then be displayed <span class="smd_featured"><a href="#" style="margin:0 8px;">Highlighted</a></span>.
smd_feat_position => Position
smd_feat_prefs => Preferences
smd_feat_prefs_some_explain => This is either a new installation or a different version<br />of the plugin to one you had before.
smd_feat_prefs_some_opts => Click "Install table" to add or update the table<br />leaving all existing data untouched.
smd_feat_prefs_some_tbl => Not all table info available.
smd_feat_pref_boxsize => Box size: 
smd_feat_pref_boxsizeby => x
smd_feat_pref_display => Display: 
smd_feat_pref_sort => Sort by: 
smd_feat_saved => Article info updated
smd_feat_search_live => Article live search
smd_feat_search_standard => Article search
smd_feat_section_list => Articles from sections: 
smd_feat_show_ui => Permit entry of:
smd_feat_tab_name => Featured articles
smd_feat_tbl_installed => Table installed
smd_feat_tbl_install_lbl => Install table
smd_feat_tbl_not_installed => Table not installed
smd_feat_tbl_not_removed => Table not removed
smd_feat_tbl_removed => Table removed
smd_feat_textile => Apply textile to: 
smd_feat_title => Title
smd_feat_unfeature_confirm => Are you sure you want to remove this article from the featured list?
smd_feat_unlabelled => [unlabelled]
EOT;

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_featured
 *
 * A Textpattern CMS plugin for pimping your articles on landing pages.
 *
 * @author Stef Dawson
 * @link   http://stefdawson.com/
 */
if (txpinterface === 'admin') {
    global $smd_featured_event, $smd_featured_pref_privs;

    $smd_featured_event = 'smd_featured';
    $smd_featured_privs = get_pref('smd_featured_privs', '1,2');
    $smd_featured_pref_privs = array(
        'all' => array(
            'smd_featured_display',
            'smd_featured_box_size',
            'smd_featured_sort',
        ),
        '1' => array(
            'smd_featured_section_list',
            'smd_featured_textile',
            'smd_featured_show_ui',
        ),
    );

    add_privs($smd_featured_event, $smd_featured_privs);
    register_tab("content", $smd_featured_event, gTxt('smd_feat_tab_name'));
    register_callback('smd_featured_manage', $smd_featured_event);
    register_callback('smd_featured_welcome', 'plugin_lifecycle.smd_featured');
    register_callback('smd_featured_inject_css', 'admin_side', 'head_end');
}

if (!defined('SMD_FEAT')) {
    define("SMD_FEAT", 'smd_featured');
}

// **********
// ADMIN SIDE
// **********
// -------------------------------------------------------------
// CSS definitions: hopefully kind to themers
function smd_featured_get_style_rules()
{
    $sizes = do_list(get_pref('smd_featured_box_size', '150x40', 1), 'x');
    $sizes[0] = (isset($sizes[0]) && is_numeric($sizes[0])) ? $sizes[0] : '150';
    $sizes[1] = (isset($sizes[1]) && is_numeric($sizes[1])) ? $sizes[1] : '40';
    $stylekeys = array('{bw}', '{bh}');
    $stylevals = array($sizes[0], $sizes[1]);

    $smd_featured_styles = array(
        'common' => '
#smd_all h3 { margin:15px 0; }
#smd_featured_cpanel select, #smd_featured_cpanel input[type="text"] { margin-bottom:10px; }
#smd_featured_cpanel input[type="text"] { padding:3px; }
#control input, #control select { margin:0; }
.smd_hidden { display:none; }
.smd_featured { background-color:#e2dfce; position:relative; }
.smd_featured a { font-weight: bold; color:#80551e; }
#smd_featured_cpanel form { border:1px solid #ccc; margin: 0.5rem 0; padding: 1rem; }
.smd_featured_subpanel h4 { margin: 0 0 0.5rem }
.smd_featured_table { margin:0 auto; text-align:center; }
.smd_clear { clear:both; }
.smd_featured_table div { display:inline-block; width:{bw}px; height:{bh}px; border:1px solid #aaa; padding:0.4em; overflow:hidden; }
.smd_feat_edart { position:absolute; right:5px; bottom:3px; }
.smd_feat_edpos { position:absolute; left:2px; bottom:2px; font-size:75%; width:7em;}
#smd_feat_saveform { margin:1em; }
#smd_feat_loading { color:#80551e; text-align:center; font-size:200%; font-style:italic; width:500px; height:100px; margin:0 auto; border:1px solid #777; background:#e2dfce;display:block }
',
    );

    return str_replace($stylekeys, $stylevals, $smd_featured_styles);
}

// -------------------------------------------------------------
function smd_featured_inject_css($evt, $stp)
{
    global $smd_featured_event, $event;

    if ($event === $smd_featured_event) {
        $smd_featured_styles = smd_featured_get_style_rules();
        echo '<style type="text/css">', $smd_featured_styles['common'], '</style>';
    }

    return;
}
// ------------------------
function smd_featured_manage($evt, $stp)
{
    if ($stp == 'save_pane_state') {
        smd_featured_save_pane_state();
    } else {
        if(!$stp or !in_array($stp, array(
                'smd_featured_table_install',
                'smd_featured_table_remove',
                'smd_featured_prefsave',
                'smd_featured_save',
                'smd_featured_store_pos',
                'smd_featured_tagit',
                'smd_featured_change_pageby',
            ))) {
            smd_featured_list('');
        } else $stp();
    }
}

// ------------------------
function smd_featured_welcome($evt, $stp)
{
    $msg = '';
    switch ($stp) {
        case 'installed':
            smd_featured_table_install(0);
            // Remove some of the per-user prefs on upgrade from v0.3x to v0.40
            safe_delete ('txp_prefs', "name IN ('smd_featured_textile') AND user_name != ''");
            $msg = 'Thanks for installing smd_featured. Please read the docs :-)';
            break;
        case 'deleted':
            smd_featured_table_remove(0);
            break;
    }
    return $msg;
}

// ------------------------
function smd_featured_list($msg = '')
{
    global $smd_featured_event, $smd_featured_list_pageby, $txp_user, $smd_featured_pref_privs;

    pagetop(gTxt('smd_feat_tab_name'), $msg);
    extract(gpsa(array('smd_feat_id', 'smd_feat_label', 'smd_feat_searchkeep', 'smd_feat_filtkeep', 'page')));

    if (smd_featured_table_exist(1)) {
        smd_featured_table_upgrade();
        $featlist = safe_rows('*', SMD_FEAT, '1=1');
        $featlist = empty($featlist) ? array() : $featlist;
        $editname = $feat_label = $feat_title = $feat_desc = '';

        $etypes = $ftypes = $flist = array();
        $etypes[''] = gTxt('smd_feat_unlabelled');
        $ftypes['smd_unlabelled'] = gTxt('smd_feat_unlabelled');
        foreach ($featlist as $item) {
            if (isset($item['label']) && !empty($item['label'])) {
                $ftypes[$item['label']] = $etypes[$item['label']] = $item['label'];
            }
            $flist[$item['feat_id']] = array(
                'label' => $item['label'],
                'position' => $item['feat_position'],
                'title' => $item['feat_title'],
                'title_html' => $item['feat_title_html'],
                'description' => $item['description'],
                'desc_html' => $item['desc_html']
            );
        }

        $featlist = $flist;

        $privs = safe_field('privs', 'txp_users', "name = '".doSlash($txp_user)."'");
        $rights = array_key_exists($privs, $smd_featured_pref_privs);

        // Get additional filtering from hidden prefs
        $where = array('1=1');
        $seclist = get_pref('smd_featured_section_list', '', 1);
        if (!$seclist) {
            $seclist = gps('section_list');
        }
        if ($seclist) {
            $where[] = "Section IN ('".join("','", do_list($seclist))."')";
        }   
        $where[] = "Status IN (4,5)";

        $orderby = get_pref('smd_featured_sort', 'Posted desc', 1);
        $orderqry = ($orderby) ? ' ORDER BY '.doSlash($orderby) : '';
        $currOrder = explode(' ', $orderby);
        $currOrder[0] = (isset($currOrder[0])) ? $currOrder[0] : 'Posted';
        $currOrder[1] = (isset($currOrder[1])) ? $currOrder[1] : 'desc';
        $display = get_pref('smd_featured_display', 'all', 1);

        // Generate the extra criteria if in list view
        if ($display === 'paginated') {
            if ($smd_feat_searchkeep) {
                $items = do_list($smd_feat_searchkeep, ' ');
                $itlist = array();
                foreach ($items as $item) {
                    if (trim($item) != '') {
                        $itlist[] = "Title like '%$item%'";
                    }
                }
                $where[] = '('.join(' OR ', $itlist).')';
            }
            if ($smd_feat_filtkeep) {
                $lbl = ($smd_feat_filtkeep == 'smd_unlabelled') ? '' : $smd_feat_filtkeep;
                $ids = safe_column('feat_id', SMD_FEAT, "BINARY label = '$lbl'");
                $where[] = "ID IN ('".join("','", $ids)."')";
            }
        }

        $where = join(' AND ', $where);
        $total = safe_count('textpattern', $where);
        $do_pag = !$smd_feat_filtkeep && !$smd_feat_searchkeep;

        $limit = ($display=='paginated' && $do_pag) ? max($smd_featured_list_pageby, 15) : 99999;
        list($page, $offset, $numPages) = pager($total, $limit, $page);

        $rs = safe_rows('*', 'textpattern', $where. $orderqry . " limit $offset, $limit");
        $out = array();

        foreach ($rs as $row) {
            $ftype = isset($featlist[$row['ID']]['label']) ? $featlist[$row['ID']]['label'] : '';
            $out[] = array($row['ID'], $row['Title'], $ftype);
            if ($smd_feat_id && $row['ID'] == $smd_feat_id) {
                $editname = $row['Title'];
                $feat_label = $featlist[$smd_feat_id]['label'];
                $feat_title = $featlist[$smd_feat_id]['title'];
                $feat_position = $featlist[$smd_feat_id]['position'];
                $feat_desc = doStrip(str_replace('\r\n','
', $featlist[$smd_feat_id]['description'])); // Hackish newline kludge
            }
            // Add the position to the most recent array entry
            if (isset($featlist[$row['ID']]['position'])) {
                $out[count($out)-1][] = $featlist[$row['ID']]['position'];
            }
        }

        //TODO: i18n
        $sortopts = array(
            'Posted' => 'Posted',
            'Expires' => 'Expiry',
            'LastMod' => 'Modified',
            'Title' => 'Title',
            'Section' => 'Section',
            'Category1' => 'Category1',
            'Category2' => 'Category2',
        );
        $sortdirs = array(
            'asc' => 'Ascending',
            'desc' => 'Descending',
        );
        $displayopts = array(
            'all' => 'All',
            'paginated' => 'Paginated',
        );
        $textileonoff = explode(',', get_pref('smd_featured_textile', 'title,desc', 1));
        $showonoff = explode(',', get_pref('smd_featured_show_ui', 'label,title,desc', 1));
        $txt_ttl = in_array('title', $textileonoff);
        $txt_desc = in_array('desc', $textileonoff);
        $show_lbl = in_array('label', $showonoff);
        $show_ttl = in_array('title', $showonoff);
        $show_desc = in_array('desc', $showonoff);
        $use_edit = ($show_lbl || $show_ttl || $show_desc) ? '1' : '0';

        $sizes = do_list(get_pref('smd_featured_box_size', '150x40', 1), 'x');
        $sizes[0] = (isset($sizes[0]) && is_numeric($sizes[0])) ? $sizes[0] : '150';
        $sizes[1] = (isset($sizes[1]) && is_numeric($sizes[1])) ? $sizes[1] : '40';
        $display_js = ($display=='all') ? 1 : 0;

        echo n.'<div id="smd_feat_loading"><div id="smd_feat_loading_holder">Loading...</div></div>';
        echo n.'<div id="smd_container" class="txp-container" style="display:none;">';
        echo n.'<div id="smd_featured_control_panel"><span class="txp-summary lever'.(get_pref('pane_smd_featured_cpanel_visible') ? ' expanded' : '').'"><a href="#smd_featured_cpanel">'.gTxt('smd_feat_control_panel').'</a></span>';
        echo n.'<div id="smd_featured_cpanel" class="toggle" style="display:'.(get_pref('pane_smd_featured_cpanel_visible') ? 'block' : 'none').'">';

        echo n.'<form id="smd_feat_prefs" action="index.php" method="post">';
        echo n.'<div id="smd_featured_prefs" class="smd_featured_subpanel"><h4>'.gTxt('smd_feat_prefs').'</h4>';

        echo n.'<div class="smd_featured_pref">';
        echo n.'<label for="smd_feat_display">'.gTxt('smd_feat_pref_display').'</label>'.
            n.selectInput('smd_feat_display', $displayopts, $display, '', '', 'smd_feat_display');
        echo n.'</div>';

        echo n.'<div class="smd_featured_pref">';
        echo n.'<label for="smd_feat_sort">'.gTxt('smd_feat_pref_sort').'</label>'.
            n.selectInput('smd_feat_sort', $sortopts, $currOrder[0], '', '', 'smd_feat_sort').
            n.selectInput('smd_feat_sortdir', $sortdirs, $currOrder[1], '', '', 'smd_feat_sortdir');
        echo n.'</div>';

        echo n.'<div class="smd_featured_pref">';
        echo n.'<div id="smd_feat_display_box">';
        echo n.'<label for="smd_feat_boxsize">'.gTxt('smd_feat_pref_boxsize').'</label>'.
            n.fInput('text', 'smd_feat_box_x', $sizes[0], '', '', '', '3', '', 'smd_feat_boxsize').n.
            gTxt('smd_feat_pref_boxsizeby').n.fInput('text', 'smd_feat_box_y', $sizes[1], '', '', '', '3');
        echo n.'</div>';

        if ($rights) {
            echo n.'<div class="smd_featured_pref smd_feat_show_ui">';
            echo n.'<label for="smd_feat_show_ui">'.gTxt('smd_feat_show_ui').'</label>';
            echo n.checkbox('smd_feat_show_ui[]', 'label', $show_lbl, '', 'smd_feat_show_label').sp.gTxt('smd_feat_label');
            echo n.checkbox('smd_feat_show_ui[]', 'title', $show_ttl, '', 'smd_feat_show_title').sp.gTxt('smd_feat_title');
            echo n.checkbox('smd_feat_show_ui[]', 'desc', $show_desc, '', 'smd_feat_show_desc').sp.gTxt('smd_feat_desc');
            echo n.'</div>';

            echo n.'<div class="smd_featured_pref smd_feat_textile">';
            echo n.'<label for="smd_feat_textile">'.gTxt('smd_feat_textile').'</label>';
            echo n.checkbox('smd_feat_textile[]', 'title', $txt_ttl, '', 'smd_feat_textile_title').sp.gTxt('smd_feat_title');
            echo n.checkbox('smd_feat_textile[]', 'desc', $txt_desc, '', 'smd_feat_textile_desc').sp.gTxt('smd_feat_desc');
            echo n.'</div>';

            echo n.'<div class="smd_featured_pref smd_feat_section_list">';
            echo n.'<label for="smd_feat_section_list">'.gTxt('smd_feat_section_list').'</label>';
            echo n.fInput('text', 'smd_feat_section_list', $seclist, '', '', '', '30', '', 'smd_feat_section_list');
            echo n.'</div>';
        }

        echo n.'</div>';

        echo n.eInput($smd_featured_event).sInput('smd_featured_prefsave');
        echo n.fInput('submit', '', gTxt('save'), 'smd_featured_save');
        echo n.'</div>';
        echo n.'</form>';

        echo n.'<form id="smd_feat_filtform" action="index.php" method="post" onsubmit="smd_featured_editkeep(0);return false;">';
        echo n.'<div id="smd_featured_filt" class="smd_featured_subpanel"><h4>'.gTxt((($display=='all') ? 'smd_feat_search_live' : 'smd_feat_search_standard')).'</h4>';
        echo n.'<label for="smd_feat_search">'.gTxt('smd_feat_by_name').'</label>'.n.fInput('text', 'smd_feat_search', $smd_feat_searchkeep, '', '', '', '', '', 'smd_feat_search').
            (($ftypes) ? n.'<div id="smd_featured_bylabel">'.gTxt('smd_feat_by_label').n.selectInput('smd_feat_filt', $ftypes, $smd_feat_filtkeep, 1, '', 'smd_feat_filt').'</div>' : '');
        echo ($display=='paginated') ? n.fInput('submit', '', gTxt('go'), 'smd_featured_save', '', 'return smd_featured_editkeep(0);') : '';
        echo n.eInput($smd_featured_event).n.sInput('smd_featured_list');
        echo n.'</div>';
        echo n.'</form>';

        echo n.'</div>';
        echo n.'</div>';

        echo n.'<form id="smd_feat_editform" action="index.php" method="post">';
        echo n.hInput('smd_feat_searchkeep', '').
                n.hInput('smd_feat_filtkeep', '').
                n.hInput('smd_feat_id', '').
                n.eInput($smd_featured_event).
                n.sInput('smd_featured_list');
        echo n.'</form>';

        echo n.'<form id="smd_feat_saveform" action="index.php" method="post" onsubmit="return smd_featured_savekeep();">';
        echo n.startTable();
        if ($smd_feat_id) {
            echo n.tr(
                n.td('&nbsp;').tdcs(gTxt('edit').sp.strong(eLink('article', 'edit', 'ID', $smd_feat_id, $editname)), 2).
                n.td(fInput('submit', '', gTxt('save'))).
                n.hInput('smd_feat_searchkeep', '').
                n.hInput('smd_feat_filtkeep', '').
                n.hInput('smd_feat_id', $smd_feat_id).
                n.eInput($smd_featured_event).
                n.sInput('smd_featured_save')
            );
            if ($show_lbl) {
                echo n.tr(
                    n.fLabelCell(gTxt('label'), '', 'smd_feat_label').
                    n.td(
                        n.fInput('text', 'smd_feat_label', $feat_label, '', '', '', '', '', 'smd_feat_label').
                        n.selectInput('smd_feat_labelchoose', $etypes, $feat_label, 0, '', 'smd_feat_labelchoose')
                    )
                );
            }
            if ($show_ttl) {
                echo n.tr(
                    n.fLabelCell(gTxt('title'), '', 'smd_feat_title').
                    n.td(
                        n.fInput('text', 'smd_feat_title', $feat_title, '', '', '', '', '', 'smd_feat_title')
                    )
                );
            }
            if ($show_desc) {
                echo n.tr(
                    n.fLabelCell(gTxt('description'), '', 'smd_feat_desc').
                    n.td(text_area('smd_feat_desc', 80, 400, $feat_desc, 'smd_feat_desc'))
                );
            }
            echo n.tr(
                n.fLabelCell(gTxt('smd_feat_position'), '', 'smd_feat_position').
                n.td(fInput('text', 'smd_feat_position', $feat_position, '', '', '', '', '', 'smd_feat_position'))
            );
        }
        echo n.endTable();
        echo n.'</form>';

        $edbtn = small('['.gTxt('edit').']');
        $edtip = gTxt('smd_feat_lbl_edfeat');
        $edartbtn = '&rarr;';
        $edarttip = gTxt('smd_feat_lbl_edart');
        $edpostip = gTxt('smd_feat_lbl_edpos');
        $unfeattip = gTxt('smd_feat_lbl_unfeature');

        if ($out) {
            $rows = count($rs);
            $tblout = array();
            $atts = ' class="smd_featured_table smd_clear" id="smd_'.$display.'"';

            $tblout[] = hed(gTxt('smd_feat_manage_lbl', array(), 'raw'), 3, ' class="smd_clear"');
            for ($idx = 0; $idx < $rows; $idx++) {
                $isfeat = (isset($out[$idx]) && array_key_exists($out[$idx][0], $featlist));
                $cellclass = $isfeat ? ' class="smd_featured"' : '';
                $edlink = $isfeat && $use_edit ? '<a class="smd_feat_edlink" href="#" onclick="return smd_featured_editkeep(\''.$out[$idx][0].'\');" title="'.$edtip.'">'.$edbtn.'</a>'.sp : '';
                $edpos = $isfeat ? '<input name="smd_featured_position" class="smd_feat_edpos" title="'.$edpostip.'" value="'.$out[$idx][3].'" onblur="return smd_featured_store_pos(this, \''.$out[$idx][0].'\')" />' : '';
                $edart = $isfeat ? '<a class="smd_feat_edart" href="?event=article&step=edit&ID='.$out[$idx][0].'" title="'.$edarttip.'">'.$edartbtn.'</a>' : '';
                $rowdata = (isset($out[$idx])) ? '<span name="smd_feat_name" class="smd_hidden">'.$out[$idx][1].'</span>' : '';
                $rowdata .= $isfeat ? '<span name="smd_feat_label" class="extra smd_hidden">'.$out[$idx][2].'</span>' : '';
                $tblout[] = (isset($out[$idx])) ? '<div'.$cellclass.'>'.$edlink.'<a href="#" title="'.(($isfeat) ? $unfeattip : '').'" onclick="return smd_featured_select(this, \''.$out[$idx][0].'\')">'.$out[$idx][1].'</a>'.$edpos.$edart.$rowdata.'</div>' : '<div></div>';
            }
            echo tag(join("",$tblout), 'div', $atts);
            echo '<div class="smd_clear"></div>';
            if ($display=='paginated' && $do_pag) {
                echo n.'<div id="'.$smd_featured_event.'_navigation" class="txp-navigation">'.
                    n.nav_form($smd_featured_event, $page, $numPages, '', '', '', '', $total, $limit).
                    n.pageby_form($smd_featured_event, $smd_featured_list_pageby);
                    n.'</div>';
            }
        }

        $qs = array(
            "event" => $smd_featured_event,
        );

        $qsVars = "index.php".join_qs($qs);
        $verifyTxt = gTxt('smd_feat_unfeature_confirm');

        echo <<<EOJS
<script type="text/javascript">
function smd_featured_select(obj, id) {
    obj = jQuery(obj).parent();

    // N.B. Negative logic used here because we're checking the class _before_ it's been toggled
    var action = ((obj).hasClass("smd_featured")) ? 'remove' : 'add';
    if (action == 'remove') {
        if ('1' == {$use_edit}) {
            var ret = confirm('{$verifyTxt}');
            if (ret == false) {
                return false;
            }
        }
    }
    jQuery.post('{$qsVars}', { step: "smd_featured_tagit", smd_feat_id: id, smd_feat_action: action },
        function(data) {
            obj.toggleClass('smd_featured');
            if (action == 'add') {
                obj.find('a').attr('title', "{$unfeattip}");
                if ('1' == '{$use_edit}') {
                    obj.prepend('<a class="smd_feat_edlink" title="{$edtip}" href="#" onclick="return smd_featured_editkeep(\''+id+'\')">{$edbtn}</a>&nbsp;');
                }
                obj.append('<input name="smd_featured_position" class="smd_feat_edpos" title="{$edpostip}" value="" onblur="return smd_featured_store_pos(this, \''+id+'\')" />');
                obj.append('<a class="smd_feat_edart" title="{$edarttip}" href="?event=article&step=edit&ID='+id+'">{$edartbtn}</a>');
                obj.append('<span name="smd_feat_label" class="extra smd_hidden"></span>');
                obj.find('input.smd_feat_edpos').focus();
            } else {
                obj.find('a.smd_feat_edlink').remove();
                obj.find('input.smd_feat_edpos').remove();
                obj.find('a.smd_feat_edart').remove();
                obj.find('a').attr('title', '');
                obj.find('span.extra').remove();
                if (jQuery("#smd_feat_search").val() != '') jQuery("#smd_feat_search").keyup();
                if (jQuery("#smd_feat_filt").val() != '') jQuery("#smd_feat_filt").change();
            }
        }
    );
    return false;
}

function smd_featured_editkeep(id) {
    jQuery("#smd_feat_editform [name='smd_feat_searchkeep']").val(jQuery("#smd_feat_search").val());
    jQuery("#smd_feat_editform [name='smd_feat_filtkeep']").val(jQuery("#smd_featured_bylabel #smd_feat_filt option:selected").val());
    jQuery("#smd_feat_editform [name='smd_feat_id']").val(id);
    jQuery("#smd_feat_editform").submit();
}

function smd_featured_savekeep() {
    jQuery("#smd_feat_saveform [name='smd_feat_searchkeep']").val(jQuery("#smd_feat_search").val());
    jQuery("#smd_feat_saveform [name='smd_feat_filtkeep']").val(jQuery("#smd_featured_bylabel #smd_feat_filt option:selected").val());
}

function smd_feat_filter(selector, query, nam, csense, exact) {
    if ({$display_js} == 1) {
        var query = jQuery.trim(query);
        csense = (csense) ? "" : "i";
        query = query.replace(/ /gi, '|'); // add OR for regex query
        if (exact) {
            tmp = query.split('|');
            for (var idx = 0; idx < tmp.length; idx++) {
                tmp[idx] = '^'+tmp[idx]+'$';
            }
            query = tmp.join('|');
        }
        var re = new RegExp(query, csense);
        jQuery(selector).each(function() {
            sel = (typeof nam=="undefined" || nam=='') ? jQuery(this) : jQuery(this).find("span[name='"+nam+"']");
            if (query == '') {
                if (sel.length == 1 && sel.text() == '') {
                    jQuery(this).show();
                } else {
                    jQuery(this).hide();
                }
            } else {
                if (sel.text().search(re) < 0) {
                    jQuery(this).hide();
                } else {
                    jQuery(this).show();
                }
            }
        });
    }
}

function smd_featured_store_pos(obj, id) {
    var obj = jQuery(obj);
    var pos = obj.val();

    //TODO: feedback on success
    sendAsyncEvent(
    {
        event: textpattern.event,
        step: 'smd_featured_store_pos',
        smd_feat_id: id,
        smd_feat_pos: pos
    });
}

jQuery(function() {
    jQuery("#smd_feat_search").keyup(function(event) {
        jQuery("#smd_feat_filt").val('0'); // Clear the filter dropdown
        // if esc is pressed or nothing is entered
        if (event.keyCode == 27 || jQuery(this).val() == '') {
            jQuery(this).val('');
            if ({$display_js} == 1) {
                jQuery(".smd_featured_table div:not(.tblhead)").show();
            }
        } else {
            smd_feat_filter('.smd_featured_table div:not(.tblhead)', jQuery(this).val(), 'smd_feat_name', 0, 0);
        }
    });
    if ('{$smd_feat_searchkeep}' != '') {
        jQuery("#smd_feat_search").keyup();
    }
    jQuery("#smd_feat_filt").change(function(event) {
        jQuery("#smd_feat_search").val(''); // Empty the search box
        if (jQuery(this).val() == '') {
            if ({$display_js} == 1) {
                jQuery('.smd_featured_table div:not(.tblhead)').show();
            }
        } else if (jQuery(this).val() == 'smd_unlabelled') {
            smd_feat_filter('.smd_featured_table div:not(.tblhead)', '', 'smd_feat_label', 0, 0);
        } else {
            smd_feat_filter('.smd_featured_table div:not(.tblhead)', jQuery(this).val(), 'smd_feat_label', 1, 1);
        }
    });
    if ('{$smd_feat_filtkeep}' != '') {
        jQuery("#smd_feat_filt").change();
    }
    if ('{$smd_feat_id}' != '') {
        jQuery("#smd_feat_label").focus();
        jQuery("#smd_feat_labelchoose").change(function(event) {
            jQuery("#smd_feat_label").val(jQuery(this).val());
        });
    }
    jQuery("#smd_feat_loading").hide();
    jQuery("#smd_container").show();
});

</script>
EOJS;

        echo '</div>';
    } else {
        // Table not installed
        $btnInstall = '<form method="post" action="?event='.$smd_featured_event.a.'step=smd_featured_table_install" style="display:inline">'.fInput('submit', 'submit', gTxt('smd_feat_tbl_install_lbl')).'</form>';
        $btnStyle = ' style="border:0;height:25px"';
        echo startTable();
        echo tr(tda(strong(gTxt('smd_feat_prefs_some_tbl')).br.br
                .gTxt('smd_feat_prefs_some_explain').br.br
                .gTxt('smd_feat_prefs_some_opts'), ' colspan="2"')
        );
        echo tr(tda($btnInstall, $btnStyle));
        echo endTable();
    }
}

// -------------------------------------------------------------
function smd_featured_change_pageby()
{
    global $smd_featured_event;

    event_change_pageby($smd_featured_event);
    smd_featured_list();
}

// ------------------------
// Update the passed record in the featured table
function smd_featured_save()
{
    global $smd_featured_event;

    extract(gpsa(array('smd_feat_id', 'smd_feat_label', 'smd_feat_title', 'smd_feat_desc', 'smd_feat_position')));
    assert_int($smd_feat_id);

    $smd_feat_titletile = $smd_feat_title;
    $smd_feat_desctile = $smd_feat_desc;
    $smd_feat_label = doSlash($smd_feat_label);
    $smd_feat_position = doSlash($smd_feat_position);

    if (smd_featured_table_exist()) {
        @include_once txpath.'/lib/classTextile.php';
        @include_once txpath.'/publish.php';

        $textileonoff = explode(',', get_pref('smd_featured_textile', ''));
        $txt_ttl = in_array('title', $textileonoff);
        $txt_desc = in_array('desc', $textileonoff);

        if (class_exists('Textile')) {
            $textile = new Textile();
            $smd_feat_titletile = doSlash((($txt_ttl) ? $textile->TextileThis(parse($smd_feat_title)) : parse($smd_feat_title)));
            $smd_feat_desctile = doSlash((($txt_desc) ? $textile->TextileThis(parse($smd_feat_desc)) : parse($smd_feat_desc)));
        }

        $smd_feat_title = doSlash($smd_feat_title);
        $smd_feat_desc = doSlash($smd_feat_desc);
        $ret = safe_upsert(SMD_FEAT, "label='$smd_feat_label', feat_position='$smd_feat_position', feat_title='$smd_feat_title', feat_title_html='$smd_feat_titletile', description='$smd_feat_desc', desc_html='$smd_feat_desctile'", "feat_id='$smd_feat_id'");
        unset($_POST['smd_feat_id']);
    }

    smd_featured_list(gTxt('smd_feat_saved'));
}

// ------------------------
// Create an empty entry in the featured table or destroy it
function smd_featured_tagit()
{
    global $smd_featured_event;
    extract(doSlash(gpsa(array('smd_feat_id', 'smd_feat_action'))));

    assert_int($smd_feat_id);

    if ($smd_feat_action == 'add') {
        $ret = safe_upsert(SMD_FEAT, "label=''", "feat_id='$smd_feat_id'");
    } else if ($smd_feat_action == 'remove') {
        $ret = safe_delete(SMD_FEAT, "feat_id='$smd_feat_id'");
    }
}

// -------------------------------------------------------------
// Stash the position against the given featured item
function smd_featured_store_pos()
{
    $id = gps('smd_feat_id');
    assert_int($id);

    $id = doSlash($id);
    $pos = doSlash(gps('smd_feat_pos'));

    $exists = safe_row('*', SMD_FEAT, "feat_id=$id");
    if ($exists) {
        safe_update(SMD_FEAT, "feat_position='$pos'", "feat_id=$id");
        send_xml_response();
    }
}

// -------------------------------------------------------------
function smd_featured_prefsave()
{
    global $smd_featured_pref_privs, $txp_user;

    // Three different types of pref can be stored: see below for details
    $stdprefs = array(
        PREF_GLOBAL => array(
            'smd_featured_section_list' => 'smd_feat_section_list',
        ),
        PREF_PRIVATE => array(
            'smd_featured_display' => 'smd_feat_display',
            'smd_featured_section_list' => 'smd_feat_section_list',
        )
    );
    $joinprefs = array(
        PREF_PRIVATE => array(
            // Index is the pref name; First item in array is the join string
            'smd_featured_box_size' => array('x', 'smd_feat_box_x', 'smd_feat_box_y'),
            'smd_featured_sort' => array(' ', 'smd_feat_sort', 'smd_feat_sortdir'),
        )
    );
    $arrayprefs = array(
        PREF_GLOBAL => array(
            'smd_featured_textile' => 'smd_feat_textile',
            'smd_featured_show_ui' => 'smd_feat_show_ui',
        )
    );

    $privs = safe_field('privs', 'txp_users', "name = '".doSlash($txp_user)."'");
    $rights = array_key_exists($privs, $smd_featured_pref_privs);
    $perprefs = ($rights) ? $smd_featured_pref_privs[$privs] : array();
    $preflist = array_merge($smd_featured_pref_privs['all'], $perprefs);

    // Standard prefs are just single widget values that are stored directly
    foreach ($stdprefs as $type => $prfs) {
        foreach ($prfs as $key => $val) {
            if (in_array($key, $preflist)) {
                set_pref(doSlash($key), doSlash(gps($val)), 'smd_featured', PREF_HIDDEN, 'text_input', 0, $type);
            }
        }
    }

    // Join prefs are discrete widget values (with different HTML names) that need combining into a single pref value
    foreach ($joinprefs as $type => $prfs) {
        foreach ($prfs as $key => $val) {
            if (in_array($key, $preflist)) {
                $joinstr = '';
                $combined = array();
                foreach ($val as $idx => $item) {
                    if ($idx==0) {
                        $joinstr = $item;
                    } else {
                        $combined[] = doSlash(gps($item));
                    }
                }
                set_pref(doSlash($key), join($joinstr, $combined), 'smd_featured', PREF_HIDDEN, 'text_input', 0, $type);
            }
        }
    }

    // Array prefs are widget values from combined checkboxes under the same HTML name.
    // They may not be presented as arrays if only one item is checked
    foreach ($arrayprefs as $type => $prfs) {
        foreach ($prfs as $key => $val) {
            if (in_array($key, $preflist)) {
                $joinstr = ',';
                $val = doSlash(gps($val));
                $combined = join($joinstr, ((is_array($val)) ? $val : array($val)));
                set_pref(doSlash($key), $combined, 'smd_featured', PREF_HIDDEN, 'text_input', 0, $type);
            }
        }
    }
    smd_featured_list(gTxt('preferences_saved'));
}

// -------------------------------------------------------------
function smd_featured_save_pane_state()
{
    $panes = array('smd_featured_cpanel');
    $pane = gps('pane');
    if (in_array($pane, $panes))
    {
        set_pref("pane_{$pane}_visible", (gps('visible') == 'true' ? '1' : '0'), 'smd_featured', PREF_HIDDEN, 'yesnoradio', 0, PREF_PRIVATE);
        send_xml_response();
    } else {
        send_xml_response(array('http-status' => '400 Bad Request'));
    }
}

// ------------------------
// Add featured table if not already installed
function smd_featured_table_install($showpane = '1')
{
    $GLOBALS['txp_err_count'] = 0;
    $ret = '';
    $sql = array();
    $sql[] = "CREATE TABLE IF NOT EXISTS `".PFX.SMD_FEAT."` (
        `feat_id`         int(8)       NOT NULL default 0,
        `label`           varchar(32)  NULL     default '',
        `feat_position`   varchar(16)  NULL     default '',
        `feat_title`      varchar(255) NULL     default '',
        `feat_title_html` varchar(255) NULL     default '',
        `description`     varchar(255) NULL     default '',
        `desc_html`       varchar(255) NULL     default '',
        PRIMARY KEY (`feat_id`)
    ) ENGINE=MyISAM";

    if (gps('debug')) {
        dmp($sql);
    }

    foreach ($sql as $qry) {
        $ret = safe_query($qry);
        if ($ret===false) {
            $GLOBALS['txp_err_count']++;
            echo "<b>".$GLOBALS['txp_err_count'].".</b> ".mysql_error()."<br />\n";
            echo "<!--\n $qry \n-->\n";
        }
    }

    // Spit out results
    if ($GLOBALS['txp_err_count'] == 0) {
        if ($showpane) {
            $message = gTxt('smd_feat_tbl_installed');
            smd_featured_list($message);
        }
    } else {
        if ($showpane) {
            $message = gTxt('smd_feat_tbl_not_installed');
            smd_featured_list($message);
        }
    }
}

// ------------------------
// Drop table if in database
function smd_featured_table_remove()
{
    $ret = '';
    $sql = array();
    $GLOBALS['txp_err_count'] = 0;
    if (smd_featured_table_exist()) {
        $sql[] = "DROP TABLE IF EXISTS " .PFX.SMD_FEAT. "; ";
        if(gps('debug')) {
            dmp($sql);
        }
        foreach ($sql as $qry) {
            $ret = safe_query($qry);
            if ($ret===false) {
                $GLOBALS['txp_err_count']++;
                echo "<b>".$GLOBALS['txp_err_count'].".</b> ".mysql_error()."<br />\n";
                echo "<!--\n $qry \n-->\n";
            }
        }
    }
    if ($GLOBALS['txp_err_count'] == 0) {
        $message = gTxt('smd_feat_tbl_removed');
    } else {
        $message = gTxt('smd_feat_tbl_not_removed');
        smd_featured_list($message);
    }
}

// ------------------------
// Handle upgrades from previous versions.
function smd_featured_table_upgrade()
{
    global $DB;

    $varCharSize = (version_compare($DB->version, '5.0.3', '>=') ? '16384' : '255');
    $colInfo = getRows('describe `'.PFX.SMD_FEAT.'`');
    $cols = array();
    $descTypes = array();

    foreach ($colInfo as $row) {
        $cols[] = $row['Field'];

        if (in_array($row['Field'], array('description', 'desc_html'))) {
            $descTypes[$row['Field']] = $row['Type'];
        }
    }

    if (!in_array('feat_id', $cols)) {
        safe_alter(SMD_FEAT, "CHANGE `id` `feat_id` int( 8 ) NOT NULL DEFAULT '0'");
    }
    if (!in_array('feat_title', $cols)) {
        safe_alter(SMD_FEAT, "ADD `feat_title` varchar( 255 ) NULL DEFAULT '' AFTER `label`");
    }
    if (!in_array('feat_title_html', $cols)) {
        safe_alter(SMD_FEAT, "ADD `feat_title_html` varchar( 255 ) NULL DEFAULT '' AFTER `feat_title`");
    }
    if (!in_array('feat_position', $cols)) {
        safe_alter(SMD_FEAT, "ADD `feat_position` varchar ( 16 ) NULL DEFAULT '' AFTER `label`");
    }
    if ($descTypes['description'] === 'text') {
        safe_alter(SMD_FEAT, "MODIFY `description` varchar (" . $varCharSize . ") NULL");
    }
    if ($descTypes['desc_html'] === 'text') {
        safe_alter(SMD_FEAT, "MODIFY `desc_html` varchar (" . $varCharSize . ") NULL");
    }
}

// ------------------------
function smd_featured_table_exist($all = '')
{
    if ($all) {
        $tbls = array(SMD_FEAT => 7);
        $out = count($tbls);

        foreach ($tbls as $tbl => $cols) {
            if (gps('debug')) {
                echo "++ TABLE ".$tbl." HAS ".count(@safe_show('columns', $tbl))." COLUMNS; REQUIRES ".$cols." ++".br;
            }
            if (count(@safe_show('columns', $tbl)) == $cols) {
                $out--;
            }
        }
        return ($out === 0) ? 1 : 0;
    } else {
        if (gps('debug')) {
            echo "++ TABLE ".SMD_FEAT." HAS ".count(@safe_show('columns', SMD_FEAT))." COLUMNS;";
        }
        return (@safe_show('columns', SMD_FEAT));
    }
}

// ****************
// PUBLIC SIDE TAGS
// ****************
// ------------------------
function smd_featured($atts, $thing)
{
    global $smd_featured_info, $smd_prior_featured, $prefs;

    extract(lAtts(array(
        'label'    => '',
        'unlabel'  => 'Featured',
        'labeltag' => '',
        'time'     => 'past',
        'status'   => '4,5',
        'section'  => '',
        'history'  => '1',
        'limit'    => '10',
        'sort'     => 'feat_position asc, Posted desc',
        'form'     => '',
        'wraptag'  => '',
        'break'    => '',
        'class'    => '',
        'html_id'  => '',
        'debug'    => '',
    ),$atts));

    $unlabel = trim($unlabel);
    // Use isset() because unlabelled articles ($label="") is a valid user option
    if (isset($atts['label'])) {
        $label = trim($label);
        if ($label) {
            $where = "BINARY label REGEXP '".$label."'";
        } else {
            $where = "label=''";
        }
        unset($atts['label']);
    } else {
        // If no label attribute has been given, treat this as 'all featured items'
        $ids = safe_column('feat_id', SMD_FEAT, '1=1');
        $ids = join("','", doSlash($ids));
        $where = "smdfeat.feat_id IN ('$ids')";
    }

    // Exclude previously seen articles
    if ($history && !empty($smd_prior_featured)) {
        $where .= ' AND smdfeat.feat_id NOT IN(' . join(',', $smd_prior_featured) . ')';
    }

    // NOTE: time is left in the $atts array and passed to article_custom. Otherwise the default
    // time value (past) will be used
    if ($time) {
        switch ($time) {
            case 'any':
                break;
            case 'future':
                $where .= " AND Posted > now()";
                break;
            default:
                $where .= " AND Posted <= now()";
        }
        if (!$prefs['publish_expired_articles']) {
            $where .= " AND (now() <= Expires OR Expires = ".NULLDATETIME.")";
        }
    }

    if ($status) {
        $where .= ' AND Status IN ('.join(',', do_list($status)).')';
        unset($atts['status']);
    }

    if ($section) {
        $where .= " AND Section IN ('".join("','", do_list($section))."')";
        unset($atts['section']);
    }

    if ($sort) {
        $where .=' ORDER BY '.$sort;
        unset($atts['sort']);
    }

    // Leave limit in the $atts array too so it doesn't default to article_custom's value if set here
    if ($limit) {
        $where .=' LIMIT '.$limit;
    }

    // Don't pass the remaining attributes we've already handled onto the article_custom tag
    unset(
        $atts['label'],
        $atts['unlabel'],
        $atts['labeltag'],
        $atts['wraptag'],
        $atts['break'],
        $atts['class'],
        $atts['history'],
        $atts['html_id'],
        $atts['debug']
    );

    if ($debug) {
        echo '++ WHERE ++';
        dmp($where);
    }

    $rs = getRows('SELECT *, unix_timestamp(Posted) as uPosted, unix_timestamp(Expires) as uExpires, unix_timestamp(LastMod) as uLastMod FROM '.PFX.'textpattern AS txp LEFT JOIN '.PFX.SMD_FEAT.' AS smdfeat ON txp.ID=smdfeat.feat_id WHERE '.$where, $debug);

    if ($debug > 1 && $rs) {
        echo '++ RECORD SET ++';
        dmp($rs);
    }

    $truePart = EvalElse($thing, 1);
    $falsePart = EvalElse($thing, 0);

    $out = array();
    if ($rs) {
        foreach ($rs as $row) {
            $smd_featured_info['label'] = $row['label'];
            $smd_featured_info['position'] = $row['feat_position'];
            $smd_featured_info['title'] = $row['feat_title_html'];
            $smd_featured_info['description'] = $row['desc_html'];
            $atts['id'] = $row['ID'];

            $artout = article_custom($atts, $truePart);

            if ($artout) {
                $smd_prior_featured[] = $row['ID'];
                $out[] = $artout;
            }
        }
    } else {
        return parse($falsePart);
    }
    if ($out) {
        return (($labeltag) ? doLabel( ( ($label == '') ? $unlabel : $label), $labeltag ) : '')
            .doWrap($out, $wraptag, $break, $class, '', '', '', $html_id);
    }
    return '';
}

// ------------------------
function smd_unfeatured($atts, $thing)
{
    global $smd_prior_featured, $thispage, $pretext;

    $time = (isset($atts['time'])) ? $atts['time'] : '';
    $status = (isset($atts['status'])) ? $atts['status'] : 4;
    $section = (isset($atts['section'])) ? $atts['section'] : '';
    $history = (isset($atts['history'])) ? $atts['history'] : '1';
    unset($atts['history']);

    $where = "1=1";

    if ($time) {
        switch ($time) {
            case 'any':
                break;
            case 'future':
                $where .= " AND Posted > now()";
                break;
            default:
                $where .= " AND Posted <= now()";
        }
        if (!$prefs['publish_expired_articles']) {
            $where .= " AND (now() <= Expires OR Expires = ".NULLDATETIME.")";
        }
    }

    if ($status) {
        $where .= ' AND Status IN ('.join(',', do_list($status)).')';
    }

    if ($section) {
        $where .= " AND Section IN ('".join("','", do_list($section))."')";
    }

    // Exclude previously seen articles
    if ($history && !empty($smd_prior_featured)) {
        $where .= ' AND id NOT IN(' . join(',', $smd_prior_featured) . ')';
    }

    $offset = (isset($atts['offset'])) ? $atts['offset'] : 0;

    if (isset($atts['limit']) && isset($atts['pageby'])) {
        $limit = $atts['limit'];
        $pageby = $atts['pageby'];
        $pageby = ($pageby == 'limit') ? $limit : $pageby;
        $pg = gps('pg');

        $grand_total = safe_count('textpattern',$where);
        $total = $grand_total - $offset;
        $numPages = ceil($total/$pageby);
        $pg = (!$pg) ? 1 : $pg;
        $pgoffset = $offset + (($pg - 1) * $pageby);
        // send paging info to txp:newer and txp:older
        $pageout['pg']          = $pg;
        $pageout['numPages']    = $numPages;
        $pageout['s']           = $pretext['s'];
        $pageout['c']           = $pretext['c'];
        $pageout['context']     = 'article';
        $pageout['grand_total'] = $grand_total;
        $pageout['total']       = $total;

        if (empty($thispage)) {
            $thispage = $pageout;
        }
    } else {
        $pgoffset = $offset;
    }

    $truePart = EvalElse($thing, 1);
    $falsePart = EvalElse($thing, 0);

    $rs = safe_column('ID', 'textpattern', $where);
    if ($rs) {
        $atts['offset'] = $pgoffset;
        $atts['id'] = join(',', $rs);
        $out = article_custom($atts, $truePart);
        return $out;
    } else {
        return parse($falsePart);
    }
}

// ------------------------
function smd_featured_info($atts)
{
    global $smd_featured_info;

    extract(lAtts(array(
        'item' => '',
    ),$atts));

    return (isset($smd_featured_info[$item]) ? $smd_featured_info[$item] : '');
}

// ------------------------
function smd_if_featured($atts, $thing)
{
    global $smd_featured_info, $thisarticle;

    extract(lAtts(array(
        'id' => '',
        'label' => '',
    ),$atts));

    if ($id) {
        $id = join("','", do_list(doSlash($id)));
    }

    if (!$id && $thisarticle) {
        $id = $thisarticle['thisid'];
    }

    $where[] = "feat_id IN ('".$id."')";

    if (isset($atts['label'])) {
        $label = explode(',', doSlash(trim($label)));
        $lblwhere = array();
        foreach ($label as $lbl) {
            $lblwhere[] = "'".trim($lbl)."'";
        }
        if ($lblwhere) {
            $where[] = '( BINARY label IN (' . join(',', $lblwhere) . ') )';
        }
    }

    $ret = safe_row('feat_id', SMD_FEAT, join(' AND', $where));

    return parse(EvalElse($thing, $ret));
}

# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_featured

Pull one or more articles out of the regular article flow and pimp them -- perhaps on a section landing page or your home page.

h2. Features

* One-click selection of articles to feature
* Label your featured articles, allowing you to group them into areas like Featured, Teasers, etc
* Add an optional title/description to each article so you can use that as copy, instead of something from the article itself (supports Textile and some TXP tags)
* Search your featured articles by name or label; filtering in real-time if you wish
* Administrators can limit the articles available for pimpage from certain sections
* Display information from the featured articles using regular TXP article tags
* Keeps track of which articles have been seen already on the page so later featured (or unfeatured) articles aren't duplicated

h2. Installation / Uninstallation

p(important). Requires Textpattern 4.6.0+

Download the plugin from either "textpattern.org":http://textpattern.org/plugins/1174/smd_featured, or the "software page":http://stefdawson.com/sw, paste the code into the TXP Admin -> Plugins pane, install and enable the plugin. Visit the "forum thread":http://forum.textpattern.com/viewtopic.php?id=33938 for more info or to report on the success or otherwise of the plugin.

When you install the plugin, the necessary table is automatically installed for you. There's currently no facility for removing it during plugin operation, but it will be deleted if the plugin is deleted from your system. Note that if you put the plugin in the cache dir and therefore don't go though the normal installation procedure, the table is *not* installed automatically; visit the _Content->Featured articles_ tab and click the _Install table_ button.

To uninstall, simply delete the plugin from the _Admin->Plugins_ page.

p(important). Unfortunately, when upgrading from v0.3x to v0.40, your Textile preferences will be lost. Please reset them by visiting the _Featured articles_ tab with an administrator account and adjusting the prefs.

h2. Configuring featured articles

Visit the _Content->Featured articles_ tab. A list of all articles (from the admin-selected sections) are presented in a grid.

h3. To feature an article

Click its name. It will be highlighted and (if permitted by the administrator) an @[Edit]@ button appears beside it. If you choose not to edit the article information, it will be featured but unlabelled.

An arrow also appears in the lower right hand corner of the box. Click this to take you to the Write tab to view/edit the original article. The same link is available by clicking the article Title while editing an entry.

In the lower left corner of the box, a position input field appears. You can type anything in this box to specify a sort order for the featured item; as soon as the cursor leaves the box the value is saved. You are free to use whichever sort scheme works for you -- numbers, alphanumerics, squiggles, anything. This does not alter the order of the items in the grid (use the _Sort by_ value in the plugin's control panel for that) but allows you to order the items as they appear on your site. You may leave this box empty if you wish. Note that sorting is performed on a per-label basis so you may reuse sort values under different labels.

Clicking @[Edit]@ will display up to four input boxes, depending on the "prefs":#smd_feat_prefs:

* *Label* : allows you to label (group) the article with others of a similar featured status. For example, you could label some articles to go in a 'teasers' block on your home page by labelling each potential article with @Teaser@. Be careful: *labels are case sensitive*. To help, you may choose a label from the dropdown list of all currently defined labels or enter a new one.
* *Title* : allows you to store some Textile-aware title related to the article. This is different from the article's actual Title and can be used for any purpose you see fit.
* *Description* : allows you to store some Textile-aware supporting information about the article. This could simply be additional information for your own use, or some copy that you may wish to display in your teasers block to entice people to read the article. It can be as simple or as complex as you like and can contain TXP tags (if they make sense to use at that point -- article tags cannot be used, for example, but @<txp:site_url />@ can).
* *Position* : allows you to store some positioning text (up to 16 characters) that governs the order this item appears on your site. This is the same value as in the little box in the lower left corner of the article cell.

When you're happy with your values, hit Save to store the entry. You can edit the information at any time by clicking the appropriate @[Edit]@ button from the featured article list.

h3. To unfeature an article

Click its name again. You may be offered a confirmation dialog box, in which case confirm that you are sure first. The article's label, title and description are deleted, so be sure you really want to remove them, or have backed the information up somewhere.

h3. Finding articles to feature

With large article lists it can become difficult to find the one you want. Above the grid is a control panel. Click it to reveal the plugin options.  On the left is the Article search area and on the right are the plugin preferences. If you are in _Display: All_ mode, simply start typing in the _By name_ box and your article list will be reduced as you type to only those that match. Type multiple words to find articles that contain any of the given words. To quickly clear the box, hit the ESCape key.

Alternatively, you can use the select list to choose all articles with a particular label. The empty entry at the top of the list means "all articles". If you have featured an article but don't supply a label you can filter it by choosing the @[unlabelled]@ item from the list. If you have labelled any articles, each label you have supplied will also be an option in the list. Remember that labels are case sensitive.

Note that filtering by name or by label are mutually exclusive actions: selecting something from the dropdown will clear the text box, and typing something in the text box will reset the dropdown.

Your search criteria are remembered between edit/list views so you won't lose your place. If you are in _Display: Paginated_ mode, the search works in exactly the same way but you have to hit Enter or click _Go_ to submit the search criteria. This mode is handy for very long article lists or slow servers / computers.

h3(#smd_feat_prefs). Plugin preferences

Users of the _Featured articles_ tab also have a few preferences at their disposal which can be altered at will:

# *Display* : choose whether you wish to display all articles on one page (and use the live search to filter them) or choose a paginated view (and use the traditional search). The traditional search might be useful if you have a lot of articles (over 1000) because the live search might slow down significantly. Note that when you search for something in paginated mode, the results themselves aren't paginated.
# *Sort by* : define the sort order of the article list.
# *Box size* : specify the @width@ and @height@ values in pixels of each cell in the grid. Default is @150x40@.

Administrators can also alter the behaviour of the plugin by altering the following prefs:

# *Permit entry of*: choose which featured elements users are permitted to enter/alter. If you disable all the checkboxes, the [Edit] links next to featured articles will not appear.
# *Apply textile to*: choose the items to which you wish to apply Textile when you Edit and Save a featured article. *Note this only affects featured articles saved from this point forward*. Thus if you wish to apply a new Textile setting to existing articles you need to visit each one in turn by clicking @[Edit]@ and then _Save_.
# *Articles from sections*: define a comma-separated list of section names. These will be the only sections from which articles are allowed to be featured. If you leave this box empty you or your users may specify @&section_list=list,of,sections,...@ in the smd_featured tab's URL to limit the list to only articles in the given sections. This is handy if you wish to direct people to the smd_featured tab from a link in another tab.

In addition, if you create a hidden pref called @smd_featured_privs@ you can limit the privilege levels of accounts that can access the _Featured articles_ tab. Default: @1,2@. If using smd_prefalizer to create this pref, set the name and value as above and set the following additional parameters:

* *type* : hidden
* *event* : smd_featured
* *input control* : text_input
* *user* : / empty /
* *position*: 0

h2. Displaying featured articles

Once you have chosen your featured articles, you need a way to display them:

h3. Tag: @<txp:smd_featured />@

Acts a bit like the standard @<txp:article />@ tag to display featured articles. Attributes:

* %(atnm)label% : display featured articles with this label. Specify @label=""@ to display unlabelled articles (which are *not* the same as unfeatured articles). If this attribute is omitted, all featured articles -- irrespective of label -- will be displayed.
* %(atnm)labeltag% : the (X)HTML tag -- without brackets -- to apply to the label attribute. If this attribute is specified the label itself is displayed at the head of your featured article list. If you wish to display unlabelled articles you need to specify the @unlabel@ attribute or you'll see no label. Note that this tag differs from the TXP convention: you will _only_ see the label if you specify the @labeltag@. You can display the label inside your articles using the @<txp:featured_info />@ tag.
* %(atnm)unlabel% : when using @label=""@ (to show unlabelled articles) this attribute specifies the label to use inside the @labeltag@. Default: "Featured".
* %(atnm)time% : limit the articles to those in the @past@, the @future@ or @any@. Default: @past@.
* %(atnm)status% : only display articles of the given status number(s). Default: @4,5@ (a.k.a. live and sticky). Note that this attribute may be ignored if you are trying to view anything other live and sticky articles (since article_custom does the same).
* %(atnm)section% : only display articles from the given section(s). If the section list is restricted using the plugin preferences, the sections used here must also be in that list.
* %(atnm)history% : determines whether to remember previously seen articles and thus exclude them from future smd_featured / smd_unfeatured tags. If you are using the same featured tag multiple times on the same page and want it to always output the same list, set this attribute to 0. Default: 1.
* %(atnm)form% : use this TXP form to display each article, instead of using the container contents.
* %(atnm)limit% : maximum number of featured articles to display. Default: 10.
* %(atnm)sort% : order the featured articles by @id@, @label@, @Title@, @Posted@, and so on. Add @asc@ or @desc@ to the attribute to alter the direction of the sort. You may also use @rand()@ to sort the articles randomly. Default: @feat_position asc, Posted desc@. The same list available for the @<txp:article />@ tag is usable here. You may also use:
** *feat_position* : your custom position
** *feat_title* : your featured title content
** *description* : your featured description content
* %(atnm)wraptag% : the (X)HTML tag -- without brackets -- to wrap around the list of featured articles.
* %(atnm)break% : the (X)HTML tag -- without brackets -- or text to put between each article.
* %(atnm)class% : the CSS class name to apply to the wraptag.
* %(atnm)html_id% : a DOM ID to apply to the wraptag.

You may use the tag as a container and any tags you specify will be displayed in each featured article. You can use standard article tags such as @<txp:title />@, @<txp:excerpt />@, @<txp:permlink />@, etc, although some articles are not displayable by Textpattern (e.g. hidden, pending and draft articles).

p(important). Note that you can't use @<txp:smd_featured>@ inside an article or an Article Form. Doing so will trigger a warning because it's the same as putting another article tag inside an article. Use the tag only in Pages, or in a Form that is never directly used to display article content.

Each time you use @<txp:smd_featured />@ it makes a note of the articles it has displayed and will not duplicate articles in later tags. You can use @<txp:else />@ in your form / container to take action if the featured list is empty.

h3. Tag: @<txp:smd_featured_info />@

Allows you to display the label or the description within your @<txp:smd_featured />@  form/container.

Has one attribute:

* %(atnm)item% : the thing you want to display. Choose from:
** *label*: the label.
** *title*: the (possibly Textile-processed) title.
** *description*: the (possibly Textile-processed) description.

h3. Tag: @<txp:smd_unfeatured />@

Use this tag if you wish to show the 'remaining' articles -- i.e. ones not already displayed by any preceding @<txp:smd_featured />@ tags. It acts very much like the built-in article_custom tag, and takes all the same attributes. Thus, by default, its context is "any articles from any section defined in the smd_featured plugin that have not been displayed already". You can reduce this list by @section@, @time@ or @status@.

Unlike article_custom, @<txp:smd_unfeatured />@ supports pagination and @<txp:else />@ (for when your unfeatured list returns nothing). You can also specify the @history@ attribute and it works exactly the same way as it does for @<txp:smd_featured />@. Thus it can be a very useful, pageable direct replacement tag for article_custom if you use @history="0"@.

Notes:

* to use pagination, remove any article tag and add the @pageby@ attribute (and of course @limit@) to the smd_unfeatured tag. Note you may get a warning about no article tag on the page, but you can ignore it.
* if you wish the @pageby@ attribute to track any @limit@ you set, specify @pageby="limit"@.
* if you have any other paging in effect on the page, unfeatured pagination will be ignored.

h3. Tag: @<txp:smd_if_featured>@

This tag allows you to detect if the current (individual) article is featured and/or has a particular label. You may also use this outside an article context by supplying a list of article IDs.

Has two attributes:

* %(atnm)id% : list of article IDs of which you wish to check the featured status. If omitted, defaults to the current article (if used inside a @<txp:article />@ container/form).
* %(atnm)label% : list of labels to compare the given ID(s) with. Specify @label=""@ to check for unlabelled articles, or use an empty item if specifying a list, e.g. @label=", Featured, Teaser"@. Remember that labels are case sensitive.

If the article matches one of the given labels the contained content will be executed. If no label is given the article(s) will be checked if they are featured, irrespective of label.

h2. Examples

h3(#eg1). Example 1: Complete directory of featured articles

bc(block). <txp:smd_featured labeltag="h2" limit="20"
     wraptag="dl" sort="label">
   <txp:if_different>
      <h2><txp:smd_featured_info item="label" /></h2>
   </txp:if_different>
   <dt>
      <txp:permlink><txp:title /></txp:permlink>
   </dt>
   <dd>
      <txp:excerpt />
   </dd>
</txp:smd_featured>

h3(#eg2). Example 2: One featured article and six teasers on the home page

Select an article and label it @feature@. Select six others and label them @teasers@. Add some description text to your teaser articles.

In your default Page, put this:

bc(block). <txp:smd_featured label="feature"
     wraptag="div" html_id="main_feature">
   <txp:permlink><txp:title /></txp:permlink>
   <txp:article_image class="feat_image" />
   <p class="leadin"><txp:excerpt /></p>
</txp:smd_featured>

bc(block). <txp:smd_featured label="teasers"
     wraptag="ul" break="li" html_id="teaser_block">
   <txp:permlink><txp:title /></txp:permlink>
   <p class="leadin"><txp:excerpt /></p>
</txp:smd_featured>

If you wanted to have some other articles beneath those, but didn't want any duplicates to appear you could use:

bc(block). <txp:smd_unfeatured form="my_list" />

h3(#eg3). Example 3: Per-section landing page featured articles

If you had a zoo site with a section for primates, a section for mammals and a section for reptiles, when you set up your featured article list define their labels like this:

* Featured primates
* Featured mammals
* Featured reptiles

On your section landing page template you can then do this to display four articles from the relevant (current) section:

bc(block). <h2>Look who's here:</h2>
<txp:smd_featured label='Featured <txp:section />'
     section='<txp:section />' limit="4"
     wraptag="div" html_id="feat_block">
   <div class="pimped_article">
      <txp:permlink><txp:title /></txp:permlink>
      <txp:smd_featured_info item="description" />
   </div>
</txp:smd_featured>

h3(#eg4). Example 4: Conditional check for featured-ness

If a site visitor has navigated away from your front page and is viewing an article, you may wish to highlight the fact the current article is a featured product. So, somewhere inside you article form add:

bc(block). <txp:smd_if_featured>
   <p class="hilight">FEATURED PRODUCT</p>
</txp:smd_if_featured>

Or if you wanted to only highlight the article if it was a featured or on special offer:

bc(block). <txp:smd_if_featured label="Featured, Special">
   <p class="hilight">As featured on the front page!</p>
</txp:smd_if_featured>

h2. Author / Credits

"Stef Dawson":http://stefdawson.com/contact

# --- END PLUGIN HELP ---
-->
<?php
}
?>