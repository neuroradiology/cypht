<?php

function message_list_meta($input, $output_mod) {
    if (!array_key_exists('list_meta', $input) || !$input['list_meta']) {
        return '';
    }
    $limit = 0;
    $since = false;
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year'
    );
    if (array_key_exists('per_source_limit', $input)) {
        $limit = $input['per_source_limit'];
    }
    if (!$limit) {
        $limit = DEFAULT_PER_SOURCE;
    }
    if (array_key_exists('message_list_since', $input)) {
        $since = $input['message_list_since'];
    }
    if (!$since) {
        $since = DEFAULT_SINCE;
    }
    $dt = sprintf('%s', strtolower($times[$since]));
    $max = sprintf('sources@%d each', $limit);

    return '<div class="list_meta">'.
        $output_mod->html_safe($dt).
        '<b>-</b>'.
        '<span class="src_count"></span> '.$output_mod->html_safe($max).
        '<b>-</b>'.
        '<span class="total"></span> total</div>';
}

function human_readable_interval($date_str) {
    $precision     = 2;
    $interval_time = array();
    $now           = time();
    $date          = strtotime($date_str);
    $interval      = $now - $date;
    $res           = array();

    $t['second'] = 1;
    $t['minute'] = $t['second']*60;
    $t['hour']   = $t['minute']*60;
    $t['day']    = $t['hour']*24;
    $t['week']   = $t['day']*7;
    $t['month']  = $t['day']*30;
    $t['year']   = $t['week']*52;

    if ($interval < 0) {
        $interval += $t['hour'];
        if ($interval < 0) {
            return 'From the future!';
        }
    }
    elseif ($interval == 0) {
        return 'Just now';
    }

    foreach (array_reverse($t) as $name => $val) {
        if ($interval_time[$name] = ($interval/$val > 0) ? floor($interval/$val) : false) {
            $interval -= $val*$interval_time[$name];
        }
    }

    $interval_time = array_slice(array_filter($interval_time, function($v) { return $v > 0; }), 0, $precision);

    foreach($interval_time as $name => $val) {
        if ($val > 1) {
            $res[] = sprintf('%d %ss', $val, $name);
        }
        else {
            $res[] = sprintf('%d %s', $val, $name);
        }
    }
    return implode(', ', $res);
}

function message_list_row($subject, $date, $timestamp, $from, $source, $id, $flags, $style, $url, $output_mod) {
        if ($style == 'email') {
            return array(
                '<tr style="display: none;" class="'.$output_mod->html_safe(str_replace(' ', '-', $id)).'">'.
                    '<td class="checkbox_cell"><input type="checkbox" value="'.$output_mod->html_safe($id).'" /></td>'.
                    '<td class="source">'.$output_mod->html_safe($source).'</td>'.
                    '<td class="from">'.$output_mod->html_safe($from).'</td>'.
                    '<td class="subject"><div class="'.$output_mod->html_safe(implode(' ', $flags)).'">'.
                        '<a href="'.$output_mod->html_safe($url).'">'.$output_mod->html_safe($subject).'</a>'.
                    '</div></td>'.
                    '<td class="msg_date">'.$date.'<input type="hidden" class="msg_timestamp" value="'.$output_mod->html_safe($timestamp).'" /></td>'.
                    '<td class="icon">'.(in_array('flagged', $flags) ? '<img alt="" src="'.Hm_Image_Sources::$star.'" width="16" height="16" />' : '').'</td>'.
                '</tr>', str_replace(' ', '-', $id));
        }
        else {
            return array(
                '<tr style="display: none;" class="'.$output_mod->html_safe($id).'">'.
                    '<td class="news_cell checkbox_cell"><input type="checkbox" value="'.$output_mod->html_safe($id).'" /></td>'.
                    '<td class="news_cell"><div class="icon">'.(in_array('flagged', $flags) ? '<img alt="" src="'.Hm_Image_Sources::$star.'" width="16" height="16" />' : '').'</div>'.
                    '<div class="subject"><div class="'.$output_mod->html_safe(implode(' ', $flags)).'">'.
                        '<a href="'.$output_mod->html_safe($url).'">'.$output_mod->html_safe($subject).'</a>'.
                    '</div></div>'.
                    '<div class="from">'.$output_mod->html_safe($from).' '.$output_mod->html_safe($source).'</div>'.
                    '<div class="msg_date">'.$date.'<input type="hidden" class="msg_timestamp" value="'.$output_mod->html_safe($timestamp).'" /></div>'.
                '</td></tr>', $id);
        }
}

function message_controls() {
    return '<a class="toggle_link" href="#" onclick="return toggle_rows();"><img alt="x" src="'.Hm_Image_Sources::$check.'" width="8" height="8" /></a>'.
        '<div class="msg_controls">'.
        '<a href="#" onclick="return message_action(\'read\');">Read</a>'.
        '<a href="#" onclick="return message_action(\'unread\');">Unread</a>'.
        '<a href="#" onclick="return message_action(\'flag\');">Flag</a>'.
        '<a href="#" onclick="return message_action(\'unflag\');">Unflag</a>'.
        '<a href="#" onclick="return message_action(\'delete\');">Delete</a></div>';
}

function message_since_dropdown($since, $name) {
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year'
    );
    $res = '<select name="'.$name.'" class="message_list_since">';
    foreach ($times as $val => $label) {
        $res .= '<option';
        if ($val == $since) {
            $res .= ' selected="selected"';
        }
        $res .= ' value="'.$val.'">'.$label.'</option>';
    }
    $res .= '</select>';
    return $res;
}

function process_since_argument($val, $validate=false) {
    $date = false;
    $valid = false;
    if (in_array($val, array('-1 week', '-2 weeks', '-4 weeks', '-6 weeks', '-6 months', '-1 year'))) {
        $valid = $val;
        $date = date('j-M-Y', strtotime($val));
    }
    else {
        $val = 'today';
        $valid = $val;
        $date = date('j-M-Y');
    }
    if ($validate) {
        return $valid;
    }
    return $date;
}

function format_msg_html($str, $external_resources=false) {
    require 'third_party/HTMLPurifier.standalone.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    if (!$external_resources) {
        $config->set('URI.DisableResources', true);
        $config->set('URI.DisableExternalResources', true);
        $config->set('URI.DisableExternal', true);
    }
    $config->set('HTML.TargetBlank', true);
    $config->set('Filter.ExtractStyleBlocks.TidyImpl', true);
    $purifier = new HTMLPurifier($config);
    $res = $purifier->purify($str);
    return $res;
}

function format_msg_image($str, $mime_type) {
    return '<img alt="" src="data:image/'.$mime_type.';base64,'.chunk_split(base64_encode($str)).'" />';
}

function format_msg_text($str, $output_mod, $links=true) {
    $str = nl2br(str_replace(' ', '&#160;&#8203;', ($output_mod->html_safe($str))));
    if ($links) {
        $link_regex = "/((http|ftp|rtsp)s?:\/\/(%[[:digit:]A-Fa-f][[:digit:]A-Fa-f]|[-_\.!~\*';\/\?#:@&=\+$,\[\]%[:alnum:]])+)/m";
        $str = preg_replace($link_regex, "<a target=\"_blank\" href=\"$1\">$1</a>", $str);
    }
    return $str;
}

function build_msg_gravatar($from) {
    if (preg_match("/[\S]+\@[\S]+/", $from, $matches)) {
        $hash = md5(strtolower(trim($matches[0], " \"><'\t\n\r\0\x0B")));
        return '<img alt="" class="gravatar" src="http://www.gravatar.com/avatar/'.$hash.'?d=mm" />';
    }
}

function display_value($name, $haystack, $type=false, $default='') {
    if (!array_key_exists($name, $haystack)) {
        return $default;
    }
    $value = $haystack[$name];
    $res = false;
    if ($type) {
        $name = $type;
    }
    switch($name) {
        case 'from':
            $value = preg_replace("/(\<.+\>)/U", '', $value);
            $res = str_replace('"', '', $value);
            break;
        case 'date':
            $res = human_readable_interval($value);
            break;
        case 'time':
            $res = strtotime($value);
            break;
        default:
            $res = $value;
            break;
    }
    return $res;
}

function format_reply_text($txt) {
    return '> '.str_replace("\n", "\n> ", $txt);
}

?>