<?php

/** 
 * Stores all the functions for manipulating a checklist
 *
 * @author   David Smith <moodle@davosmith.co.uk>
 * @package  mod/checklist
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

class checklist_class {
    var $cm;
    var $course;
    var $checklist;
    var $strchecklists;
    var $strchecklist;
    var $context;
    var $userid;
    var $items;
    var $useritems;

    function checklist_class($cmid='staticonly', $userid=0, $checklist=NULL, $cm=NULL, $course=NULL) {
        global $COURSE;

        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }

        $this->userid = $userid;

        global $CFG;

        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('checklist', $cmid)) {
            error('Course Module ID was incorrect');
        }

        $this->context = get_context_instance(CONTEXT_MODULE, $this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = get_record('course', 'id', $this->cm->course)) {
            error('Course is misconfigured');
        }

        if ($checklist) {
            $this->checklist = $checklist;
        } else if (! $this->checklist = get_record('checklist', 'id', $this->cm->instance)) {
            error('assignment ID was incorrect');
        }

        $this->strchecklist = get_string('modulename', 'checklist');
        $this->strchecklists = get_string('modulenameplural', 'checklist');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strchecklist.': '.format_string($this->checklist->name,true));

        $this->get_items();
    }

    /**
     * Get an array of the items in a checklist
     * 
     */
    function get_items() {
        global $CFG;
        
        $sql = 'checklist = '.$this->checklist->id;
        $sql .= ' AND userid = 0';
        $this->items = get_records_select('checklist_item', $sql, 'position');

        if ($this->userid) {
            $sql = 'checklist = '.$this->checklist->id;
            $sql .= ' AND userid = '.$this->userid;
            $this->useritems = get_records_select('checklist_item', $sql, 'position');
        } else {
            $this->useritems = false;
        }

        // Makes sure all items are numbered sequentially, starting at 1
        $this->update_item_positions();

        if ($this->userid) {

            $sql = 'SELECT i.id, c.usertimestamp FROM '.$CFG->prefix.'checklist_item i LEFT JOIN '.$CFG->prefix.'checklist_check c ';
            $sql .= 'ON (i.id = c.item AND c.userid = '.$this->userid.') WHERE i.checklist = '.$this->checklist->id;

            // TODO - display the teacher's mark

            $checks = get_records_sql($sql);

            foreach ($checks as $check) {
                $id = $check->id;
                
                if (isset($this->items[$id])) {
                    $this->items[$id]->checked = $check->usertimestamp > 0;
                } elseif (isset($this->useritems[$id])) {
                    $this->useritems[$id] = $check->usertimestamp > 0;
                } else {
                    error('Non-existant item has been checked');
                }
            }
        }
    }

    /**
     * Check all items are numbered sequentiall from 1
     * also, move any items between $start and $end
     * the number of places indicated by $move
     *
     * @param $move (optional) - how far to offset the current positions
     * @oaram $start (optional) - where to start offsetting positions
     * @param $end (optional) - where to stop offsetting positions
     */
    function update_item_positions($move=0, $start=1, $end=false) {
        $pos = 1;
        
        foreach($this->items as $item) {
            if ($pos == $start) {
                $pos += $move;
            } else if ($pos == $end) {
                break;
            }
            
            if ($item->position != $pos) {
                $item->position = $pos;
                update_record('checklist_item', $item);
            }
            $pos++;
        }
    }

    function canupdateown() {
        return has_capability('mod/checklist:updateown', $this->context);
    }

    function canpreview() {
        return has_capability('mod/checklist:preview', $this->context);
    }

    function canedit() {
        return has_capability('mod/checklist:edit', $this->context);
    }

    function canviewreports() {
        return has_capability('mod/checklist:viewreports', $this->context);
    }
        
    function view() {
        // TODO - check sesskey()
        
        global $CFG;
        
        if ($this->canupdateown()) {
            $currenttab = 'view';
        } elseif ($this->canpreview()) {
            $currenttab = 'preview';
        } else {
            $loginurl = $CFG->wwwroot.'/login/index.php';
            if (!empty($CFG->loginhttps)) {
                $loginurl = str_replace('http:','https:', $loginurl);
            }
            echo '<br/>';
            notice_yesno('<p>' . get_string('guestsno', 'checklist') . "</p>\n\n</p>" .
                         get_string('liketologin') . '</p>', $loginurl, get_referer(false));
            print_footer($course);
            die;
        }

        $this->view_header();

        print_heading(format_string($this->checklist->name));

        $this->view_tabs($currenttab);

        if ((!$this->items) && $this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/edit.php?checklist='.$this->checklist->id, get_string('noitems','checklist'));
        }

        add_to_log($this->course->id, 'checklist', 'view', "view.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);        

        if ($this->canupdateown()) {
            $this->process_view_actions();
        }

        $this->view_items();

        $this->view_footer();
    }


    function edit() {
        // TODO - check sesskey()
        
        global $CFG;
        
        if (!$this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/view.php?checklist='.$this->context->id);
        }

        add_to_log($this->course->id, "checklist", "edit", "edit.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);

        $this->view_header();

        print_heading(format_string($this->checklist->name));

        $this->view_tabs('edit');

        $this->process_edit_actions();

        $this->view_edit_items();

        $this->view_footer();
    }

    function view_header() {
        $navlinks = array();
        $navlinks[] = array('name' => $this->strchecklists, 'link' => "index.php?id={$this->course->id}", 'type' => 'activity');
        $navlinks[] = array('name' => format_string($this->checklist->name), 'link' => '', 'type' => 'activityinstance');

        $navigation = build_navigation($navlinks);

        print_header_simple($this->pagetitle, '', $navigation, '', '', true,
                            update_module_button($this->cm->id, $this->course->id, $this->strchecklist), navmenu($this->course, $this->cm));
    }

    function view_tabs($currenttab) {
        global $CFG;
        
        $tabs = array();
        $row = array();
        $inactive = array();
        $activated = array();

        if ($this->canupdateown()) {
            $row[] = new tabobject('view', "$CFG->wwwroot/mod/checklist/view.php?checklist={$this->checklist->id}", get_string('view', 'checklist'));
        } elseif ($this->canpreview()) {
            $row[] = new tabobject('preview', "$CFG->wwwroot/mod/checklist/view.php?checklist={$this->checklist->id}", get_string('preview', 'checklist'));
        }
        if ($this->canviewreports()) {
            $row[] = new tabobject('report', "$CFG->wwwroot/mod/checklist/report.php?checklist={$this->checklist->id}", get_string('report', 'checklist'));
        }
        if ($this->canedit()) {
            $row[] = new tabobject('edit', "$CFG->wwwroot/mod/checklist/edit.php?checklist={$this->checklist->id}", get_string('edit', 'checklist'));
        }

        if ($currenttab == 'view' && count($row) == 1) {
            // No tabs for students
        } else {
            $tabs[] = $row;
        }

        if ($currenttab == 'report') {
            $activated[] = 'report';
        }

        if ($currenttab == 'edit') {
            $activated[] = 'edit';

            if (!$this->items) {
                $inactive = array('view', 'report', 'preview');
            }
        }

        if ($currenttab == 'preview') {
            $activated[] = 'preview';
        }

        print_tabs($tabs, $currenttab, $inactive, $activated);
    }

    function view_items() {
        global $CFG;
        
        print_box_start('generalbox boxwidthnormal boxaligncenter');

        echo '<p>'.format_string($this->checklist->intro, $this->checklist->introformat).'</p>';
        
        if (!$this->items) {
            print_string('noitems','checklist');
        } else {
            $updateform = $this->canupdateown();
            if ($updateform) {
                echo '<form action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="get">';
                echo '<input type="hidden" name="checklist" value="'.$this->checklist->id.'" />';
                echo '<input type="hidden" name="action" value="updatechecks" />';
            }
                
            echo '<ol class="checklist">';
            foreach ($this->items as $item) {
                $itemname = '"item'.$item->id.'"';
                $checked = ($updateform && $item->checked) ? ' checked="checked" ' : '';
                echo '<li><input type="checkbox" name='.$itemname.' id='.$itemname.$checked.' />';
                echo '<label for='.$itemname.'>'.s($item->displaytext).'</label>';
                echo '</li>';
            }
            echo '</ol>';

            if ($updateform) {
                echo '<input type="submit" name="submit" value="'.get_string('savechecks','checklist').'" />';
                echo '</form>';
            }
        }

        print_box_end();
    }

    function view_edit_items() {
        global $CFG;
        
        print_box_start('generalbox boxwidthnormal boxaligncenter');
        
        echo '<ol class="checklist">';
        if ($this->items) {
            $lastitem = count($this->items);
            foreach ($this->items as $item) {
                $itemname = '"item'.$item->id.'"';
                echo '<li><input type="checkbox" name='.$itemname.' id='.$itemname.' disabled="disabled" />';

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="itemid" value="'.$item->id.'" />';
                    echo '<input type="text" name="displaytext" value="'.$item->displaytext.'" />';
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                    echo '</form>';
                } else {
                    echo '<label for='.$itemname.'>'.s($item->displaytext).'</label>&nbsp;';

                    $baseurl = $CFG->wwwroot.'/mod/checklist/edit.php?checklist='.$this->checklist->id.'&amp;itemid='.$item->id.'&amp;action=';

                    echo '<a href="'.$baseurl.'edititem" />';
                    echo '<img src="'.$CFG->pixpath.'/t/edit.gif" alt="'.get_string('edititem','checklist').'" /></a>&nbsp;';

                    if ($item->indent > 0) {
                        echo '<a href="'.$baseurl.'unindentitem" />';
                        echo '<img src="'.$CFG->pixpath.'/t/left.gif" alt="'.get_string('unindentitem','checklist').'" /></a>';
                    }

                    if ($item->indent < CHECKLIST_MAX_INDENT) {
                        echo '<a href="'.$baseurl.'indentitem" />';
                        echo '<img src="'.$CFG->pixpath.'/t/right.gif" alt="'.get_string('indentitem','checklist').'" /></a>';
                    }

                    echo '&nbsp;';
                    
                    // TODO more complex checks once there is indentation to worry about as well
                    if ($item->position > 1) {
                        echo '<a href="'.$baseurl.'moveitemup" />';
                        echo '<img src="'.$CFG->pixpath.'/t/up.gif" alt="'.get_string('moveitemup','checklist').'" /></a>';
                    }

                    if ($item->position < $lastitem) {
                        echo '<a href="'.$baseurl.'moveitemdown" />';
                        echo '<img src="'.$CFG->pixpath.'/t/down.gif" alt="'.get_string('moveitemdown','checklist').'" /></a>';
                    }
                }
                
                echo '</li>';
            }
        }
        echo '<li>';
        echo '<form action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
        echo '<input type="hidden" name="action" value="additem" />';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        echo '<input type="text" name="displaytext" value="" />';
        echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
        echo '</form>';
        echo '</li>';
        echo '</ol>';

        print_box_end();
    }

    function view_footer() {
        print_footer($this->course);
    }

    function process_view_actions() {
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }
        
        switch($action) {
        case 'updatechecks':
            $this->updatechecks();
            break;

        default:
            error('unknown action: "'.s($action).'"');
        }
    }
    
    function process_edit_actions() {
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }
        $itemid = optional_param('itemid', 0, PARAM_INT);

        switch ($action) {
        case 'additem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $this->additem($displaytext);
            break;
        case 'edititem':
            if (isset($this->items[$itemid])) {
                $this->items[$itemid]->editme = true;
            }
            break;
        case 'updateitem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $this->updateitemtext($itemid, $displaytext);
            break;
        case 'deleteitem':
            break;
        case 'updateitemtext':
            break;
        case 'moveitemup':
            break;
        case 'moveitemdown':
            break;
        case 'indentitem':
            break;
        case 'unindentitem':
            break;
        default:
            print_error('Invalid action - "'.s($action).'"');
        }
    }

    function additem($displaytext, $userid=0, $indent=0, $position=false) {
        // Create new DB record and add to items array
        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }
        
        $item = new Object();
        $item->checklist = $this->checklist->id;
        $item->displaytext = $displaytext;
        if ($position) {
            $item->position = $position;
        } else {
            $item->position = count($this->items) + 1;
        }
        $item->indent = $indent;
        $item->userid = $userid;

        $item->id = insert_record('checklist_item', $item);
        if ($item->id) {
            if ($userid) {
                $this->useritems[$item->id] = $item;
                if ($position) {
                    uasort($this->useritems, 'checklist_itemcompare');
                }
            } else {
                if ($position) {
                    $this->update_item_positions(1, $position);
                }
                $this->items[$item->id] = $item;
                uasort($this->items, 'checklist_itemcompare');
            }
        }
    }

    function updateitemtext($itemid, $displaytext) {
        // Update the text in the array and db record
        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }

        if (isset($this->items[$itemid])) {
            $this->items[$itemid]->displaytext = $displaytext;
            update_record('checklist_item', $this->items[$itemid]);
        }
    }

    function deleteitem($itemid) {
        // Remove item from DB
        // Remove all 'check' records linked to this item
    }

    function moveitemto($itemid, $newposition) {
        // Update position of item
        // Update position of all items following this one
    }

    function moveitemup($itemid) {
        // Get current position
        // If indented, only allow move if suitable space for 'reparenting'
        // Subtract 1 from position
        // call moveitemto
    }

    function moveitemdown($itemid) {
        // Get current position
        // Check not already at end of list
        // call moveitemto
    }
        
    function indentitemto($itemid, $indent) {
        // Check suitable parent for this new position
        // Update DB
    }

    function indentitem($itemid) {
        // Get current indent
        // Call indentitemto
    }

    function unindentitem($itemid) {
        // Get current indent
        // call indentitemto
    }

    function updatechecks() {
        $newchecks = array();
        
        foreach ($_REQUEST as $param => $val) {
            if (substr($param, 0, 4) == 'item') {
                $id = intval(substr($param, 4));
                $newval = clean_param($param, PARAM_BOOL);

                $newchecks[$id] = $newval;
                
            }
        }

        foreach ($this->items as $item) {
            $newval = isset($newchecks[$item->id]) && $newchecks[$item->id];

            if ($newval != $item->checked) {
                $item->checked = $newval;
                
                $check = get_record_select('checklist_check', 'item = '.$item->id.' AND userid = '.$this->userid);
                if ($check) {
                    if ($newval) {
                        $check->usertimestamp = time();
                    } else {
                        $check->usertimestamp = 0;
                    }
                    update_record('checklist_check', $check);

                } else {
                    
                    $check = new Object();
                    $check->item = $item->id;
                    $check->userid = $this->userid;
                    $check->usertimestamp = time();
                    $check->teachertimestamp = 0;
                    $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
                    
                    $check->id = insert_record('checklist_check', $check);
                }
            }
        }
    }
}

function checklist_itemcompare($item1, $item2) {
    if ($item1->position < $item2->position) {
        return -1;
    } elseif ($item1->position > $item2->position) {
        return 1;
    }
    if ($item1->id < $item2->id) {
        return -1;
    } elseif ($item1->id > $item2->id) {
        return 1;
    }
    return 0;
}



?>