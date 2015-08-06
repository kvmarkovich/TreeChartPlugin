<?php
/**
 * The template for displaying city goverment structure
 *
 *
 * @package WordPress
 */

function convertToTree(
	array $flat, $idField = 'id',
	$parentIdField = 'parentId'
) {
	$childNodesField = 'childNodes';
	$indexed = array();
	// first pass - get the array indexed by the primary id
	foreach ( $flat as $row ) {
//		var_dump($row);
		$r_id                                 = $row[ $idField ][0];
		$indexed[ $r_id ]                     = $row;
		$indexed[ $r_id ][ $childNodesField ] = array();
	}

	//second pass
	$root = null;
	foreach ( $indexed as $id => $row ) {
		$indexed[ $row[ $parentIdField ][0] ][ $childNodesField ][ $id ] =& $indexed[ $id ];
		if ( ! $row[ $parentIdField ] ) {
			$root = $id;
		}
	}

	return array( $root => $indexed[ $root ] );
}

function treeToSortedArray( array $tree ) {
	$result = array();
	foreach ( $tree as $item ) {
		if ( isset($item['childNodes']) ) {
			$childTree = $item['childNodes'];
			unset( $item['childNodes'] );
		}
		array_push( $result, $item );
		if ( ! is_null( $childTree ) ) {
			$treeToSortedArray = treeToSortedArray( $childTree );
			foreach ( $treeToSortedArray as $arr_item ) {
				array_push( $result, $arr_item );
			}
		}
	}

	return $result;
}


wp_enqueue_script( 'getorgchart' );
/*
	 * Эта функция будет вызвана только на странице плагина,
	   поставим наш стиль в очередь здесь */
wp_enqueue_style( 'getorgchart' );

get_header(); ?>

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
		<script type="text/ecmascript">
			jQuery(document).ready(jQuery('#people').getOrgChart({
				theme: "cassandra",
				primaryColumns: ["position", "name"],
				imageColumn: "image",
				gridView: false,
				editable: false,
				searchable: false,
				levelSeparation: 33,
				dataSource: [
					<?php
																$myposts = get_posts( array(
																'post_type' => 'gorsovet',
																'numberposts' => 100,
																'post_status' => 'publish',
					//											'meta_key' => 'parent_id',
					//											'orderby' => 'meta_value_num',
																'order' => 'ASC'));

																$metas = array();
																$i = 0;
																$len = count($myposts);
																foreach( $myposts as $post ) {
																	$content = $post->post_content;
																	$meta = get_post_meta( $post->ID );
																	$feat_image = wp_get_attachment_url( get_post_thumbnail_id($post->ID) );
																	$meta['feat_image'] = $feat_image;
																	array_push($metas, $meta);

										//							$content = apply_filters( 'the_content', $content );
																}

															 //echo get_page_link( $post->ID );
															//echo $post->post_title;

$convertToTree=treeToSortedArray(convertToTree($metas, 'id', 'parent_id'));

//print_r($convertToTree);

															foreach ($convertToTree as $meta) {
															$id = array_key_exists('id', $meta) ? $meta['id'][0] : '';
															$name = array_key_exists('name', $meta) ? $meta['name'][0] : '';
															$parent_id = array_key_exists('parent_id', $meta) ? $meta['parent_id'][0] : 'null';
															$position = array_key_exists('position', $meta) ? $meta['position'][0] : '';
															$contacts = array_key_exists('contacts', $meta) ? $meta['contacts'][0] : '';
															$feat_image = array_key_exists('feat_image', $meta) ? $meta['feat_image'] : '';
										//					$chartElementContent = $name." <div class=\"position\">".$position."</div><div class=\"contacts\">".$contacts."</div>";

										//					$data_element = "[{v:'".$id."', f:'".$chartElementContent."'}, '".$parent_id."', '']";

															$data_element = "{ id: ${id}, parentId: ${parent_id}, name: \"${name}\", position: \"${position}\", contacts: \"${contacts}\" , image: \"${feat_image}\" }";
					//										$data_element = "<tr><td>${id}</td><td>${parent_id}</td><td>${name}</td><td>${position}</td><td>${contacts}</td></td>images/f-46.jpg\" }";

										echo $data_element;

															if ($i < $len - 1) {
										echo ','.PHP_EOL;
															}
															$i++;
															//echo $content;
																}
															?>
				]
			}));
		</script>

	</main>
	<!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>
