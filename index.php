<?php
    require_once 'vendor/autoload.php';

    function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0) {
        // search and remove comments like /* */ and //
        $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);

        if(version_compare(phpversion(), '5.4.0', '>=')) {
            $json = json_decode($json, $assoc, $depth, $options);
        }
        elseif(version_compare(phpversion(), '5.3.0', '>=')) {
            $json = json_decode($json, $assoc, $depth);
        }
        else {
            $json = json_decode($json, $assoc);
        }

        return $json;
    }

    function render_inline($field){
        $html = '';
        foreach($field['payload'] as $f){
            if(empty($f['link']) && empty($f['style'])){
                $html .= $f['text'];
            } else {
                $f['style'] = empty($f['style']) ? array() : $f['style'];
                $styleDefault = array(
                    'bold'      => false,
                    'underline' => false,
                    'italic'    => false,
                    'strike'    => false
                );
                $style = array_merge($styleDefault,$f['style']);

                // Select the most meaningful tag
                if(!empty($f['link'])){
                    $tag = 'a';
                } else if($style['bold']) {
                    $tag = 'strong';
                    $style['bold'] = false;
                } else if($style['italic']) {
                    $tag = 'em';
                    $style['italic'] = false;
                } else {
                    $tag = 'span';
                }

                // Whip some inline style classes
                $class = '';
                foreach($style as $k => $v){
                    if($v){
                        $class .= "style-$k ";
                    }
                }

                // Make an href if needed
                $href = empty($f['link']) ? '' : 'href="' . $f['link']['href'] . '"';
                $text = $f['text'];

                $html .= "<$tag class=\"$class\" $href>$text</$tag>";
            }
            $html .= ' ';
        }

        return $html;
    }

    function render_image($field){
        if(empty($field['payload']['src'])){
            return false;
        }
        $src = 'src="' . $field['payload']['src'] . '"';
        $width = empty($field['payload']['width']) ? '' : 'width="' . $field['payload']['width'] . '"';
        $height = empty($field['payload']['height']) ? '' : 'height="' . $field['payload']['height'] . '"';

        return "<img $src $width $height />";
    }

    $loader = new Twig_Loader_Filesystem('templates');
    $twig = new Twig_Environment($loader, array(
        'debug' => true
    ));
    $twig->addExtension(new Twig_Extension_Debug());

    $render_block = new Twig_SimpleFunction('render_block', function (Twig_Environment $twig, $block) {
        $type = $block['block_type'];
        return $twig->render('blocks/' . $type . '.html.twig',array(
            'block' => $block
        ));
    }, array(
        'needs_environment' => true,
        'is_safe'           => array('html')
    ));
    $twig->addFunction($render_block);

    $render_field = new Twig_SimpleFunction('render_field', function ($field) {
        if(empty($field['field_type'])){
            return false;
        }

        if(strpos($field['field_type'],'inline_') === 0){
            return render_inline($field);
        } else if ($field['field_type'] == 'embedded_image') {
            return render_image($field);
        }

        // No match
        return false;
    }, array(
        'is_safe'           => array('html')
    ));
    $twig->addFunction($render_field);

    $twig->addGlobal('asset_base_path','/twigscratch/');

    $type = !empty($_GET['type']) ? $_GET['type'] : 'page';
    $data = json_clean_decode(file_get_contents('data/' . $type . '_data.json'),true);

    echo $twig->render($type . '.html.twig', array('data' => $data));
?>