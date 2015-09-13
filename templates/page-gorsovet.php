<?php
/**
 * The template for displaying city goverment structure
 *
 *
 * @package WordPress
 */
wp_enqueue_script('getorgchart');
/*
 * Эта функция будет вызвана только на странице плагина,
  поставим наш стиль в очередь здесь */
wp_enqueue_style('getorgchart');

// in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
//wp_localize_script('ajax-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'post_type' => 'gorsovet'));

wp_enqueue_script('ajax-gorsovet');

//get_header();
?>
<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

        <div id="people"></div>
        <style type="text/css">
            html, body {margin: 0px; padding: 0px;width: 100%;height: 100%;overflow: hidden; }
            #people {width: 100%;height: 100%; }
            /*#people .get-org-chart .get-oc-c .get-level-1 .get-box {fill:url(#level1);  stroke: #870303;  }*/
            /*#people .get-org-chart .get-oc-c .get-level-2 .get-box {fill:url(#level2);  stroke: #0D0D87;}*/
            /*#people .get-org-chart .get-oc-c .get-level-3 .get-box {fill:url(#level2);  stroke: #0D0D87;}*/
            /*#people .get-org-chart {background-color: #080808;background-image: url(images/wallpaper.jpg);background-position: top center;background-repeat: no-repeat;}*/
            /*#people .get-org-chart .get-oc-tb{background-image: url(images/bg.jpg);}*/
            /*#people .get-org-chart .get-oc-tb {border-bottom: 2px solid #000000;}*/
            /*#people .get-org-chart .get-oc-c .link{stroke: #FFFFFF;}*/
            /*#people .get-org-chart .get-oc-c .get-text {fill: #D7D7D7;}*/
            /*#people .get-org-chart .get-oc-c .get-text-0 {font-size: 30px;}*/
            /*#people .get-org-chart .get-user-logo path  {fill: url(#level2);}*/
        </style>

    </main>
    <!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>
