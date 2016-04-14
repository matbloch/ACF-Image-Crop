<?php

if( ! class_exists('acf_field_manual_image_crop') ) :
class acf_field_manual_image_crop extends acf_field_image {


    /*
    *  __construct
    *
    *  This function will setup the field type data
    *
    *  @type    function
    *  @date    5/03/2014
    *  @since   5.0.0
    *
    *  @param   n/a
    *  @return  n/a
    */

    function __construct() {

        /*
        *  name (string) Single word, no spaces. Underscores allowed
        */

        $this->name = 'manual_image_crop';


        /*
        *  label (string) Multiple words, can include spaces, visible when selecting a field type
        */

        $this->label = __('Image with user-crop', 'acf-manual_image_crop');


        /*
        *  category (string) basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
        */

        $this->category = 'content';


        /*
        *  defaults (array) Array of default settings which are merged into the field object. These are used later in settings
        */

        $this->defaults = array(
            'compression_factor' => 90,
            'image_formats' => 'auto',
            'fixed_dimension' => 'width',
            'fixed_size' => 300
        );

        $this->options = get_option( 'acf_manual_image_crop_settings' );

        // add ajax action to be able to retrieve full image size via javascript
        add_action( 'wp_ajax_acf_manual_image_crop_get_image_size', array( &$this, 'crop_get_image_size' ) );
        add_action( 'wp_ajax_acf_manual_image_crop_perform_crop', array( &$this, 'perform_crop' ) );


        // add filter to media query function to hide cropped images from media library
        //add_filter('ajax_query_attachments_args', array($this, 'filterMediaQuery'));

        /*
        *  l10n (array) Array of strings that are used in JavaScript. This allows JS strings to be translated in PHP and loaded via:
        *  var message = acf._e('manual_image_crop', 'error');
        */

        $this->l10n = array(
            'width_should_be'   => __( 'Width should be at least: ','acf-manual_image_crop' ),
            'height_should_be'  => __( 'Height should be at least: ','acf-manual_image_crop' ),
            'selected_width'    => __( 'Selected image width: ','acf-manual_image_crop' ),
            'selected_height'   => __( 'Selected image height: ','acf-manual_image_crop' ),
            'size_warning'      => __( 'Warning: The selected image is smaller than the required size!','acf-manual_image_crop' ),
            'crop_error'        => __( 'Sorry, an error occurred when trying to crop your image:')
        );

        // do not delete!
        acf_field::__construct();
        //parent::__construct();

    }

    /* enqueue scripts and styles */
    function input_admin_enqueue_scripts() {
        $dir = plugin_dir_url( __FILE__ );

        // scripts
        wp_register_script('acf-input-manual_image_crop', "{$dir}js/input.js", array('acf-input', 'imgareaselect'));
        wp_enqueue_script(array('acf-input-manual_image_crop'));
        // styles
        wp_register_style('acf-input-manual_image_crop', "{$dir}css/input.css", array('acf-input'));
        wp_enqueue_style(array('acf-input-manual_image_crop','imgareaselect'));
        //wp_localize_script( 'acf-input-manual_image_crop', 'ajax', array('nonce' => wp_create_nonce('acf_nonce')) );
    }

    function field_group_admin_enqueue_scripts() {

        $dir = plugin_dir_url( __FILE__ );

        wp_register_script('acf-input-manual-image-crop-options', "{$dir}js/options.js", array('jquery'));
        wp_enqueue_script( 'acf-input-manual-image-crop-options');

        wp_register_style('acf-input-manual-image-crop-options', "{$dir}css/options.css");
        wp_enqueue_style( 'acf-input-manual-image-crop-options');
    }


    // AJAX handlers
    public function crop_get_image_size()
    {
        $img = wp_get_attachment_image_src( $_POST['image_id'], 'full');
        if($img){
            echo json_encode( array(
                    'url' => $img[0],
                    'width' => $img[1],
                    'height' => $img[2]
                ) );
            }
        exit;
    }
    public function perform_crop(){
        $targetWidth = $_POST['target_width'];
        $targetHeight = $_POST['target_height'];
        $compression_factor = $_POST['compression_factor'];

        // fixed dimension
        if($_POST['fixed_dimension'] == 'width'){
            $targetWidth = intval($_POST['fixed_size']);
            if($_POST['img_format'] == 'auto'){
                $targetHeight = intval($targetWidth/abs($_POST['x2']-$_POST['x1'])*abs($_POST['y2']-$_POST['y1']));
            }else{
                $format = explode(':', $_POST['img_format']);
                $targetHeight = intval($targetWidth/$format[0]*$format[1]);
            }
        }else{
            $targetHeight = intval($_POST['fixed_size']);

            if($_POST['img_format'] == 'auto'){
                $targetWidth = intval($targetHeight*abs($_POST['x2']-$_POST['x1'])/abs($_POST['y2']-$_POST['y1']));
            }else{
                $format = explode(':', $_POST['img_format']);
                $targetWidth = intval($targetHeight*$format[0]/$format[1]);
            }
        }

        $imageData = $this->generate_cropped_image($_POST['id'], $_POST['x1'], $_POST['x2'], $_POST['y1'], $_POST['y2'], $targetWidth, $targetHeight, false, $_POST['preview_size'], $compression_factor, $_POST['field_name']);

        echo json_encode($imageData);

        die();
    }


    // render field
    function render_field( $field ) {

	
        // enqueue
        acf_enqueue_uploader();

        // parse data
        $imageData = $this->get_image_data($field);

        // vars
        $div_atts = array(
            'class'                   => 'acf-image-uploader acf-cf acf-manual-image-crop',
            'data-fixed_dimension'    => $field['fixed_dimension'],
            'data-fixed_size'         => $field['fixed_size'],
            'data-compression_factor' => $field['compression_factor'],
            'data-preview_size'     => $field['preview_size'],
            'data-field_name'       => $field['key'].'_id'.get_the_id()
        );

        $input_atts = array(
            'type'                  => 'hidden',
            'name'                  => $field['name'],
            'value'                 => htmlspecialchars($field['value']),
            'data-name'             => 'id',
            'data-original-image'   => $imageData->original_image,
            'data-cropped-image'    => json_encode($imageData->cropped_image),
            'class'                 => 'acf-image-value'
        );

        $input_atts = array(
            'type'                  => 'select',
            'name'                  => $field['name'],
            'value'                 => htmlspecialchars($field['value']),
            'data-name'             => 'id',
            'data-original-image'   => $imageData->original_image,
            'data-cropped-image'    => json_encode($imageData->cropped_image),
            'class'                 => 'acf-image-value'
        );

        // has value?
        if($imageData->original_image){
            $url = $imageData->preview_image_url;
            $div_atts['class'] .= ' has-value';
        }

        // render input
        ?>
        <div <?php acf_esc_attr_e( $div_atts ); ?>>

            <!-- hidden data -->
            <div class="acf-hidden">
                <input <?php acf_esc_attr_e( $input_atts ); ?>/>
            </div>

            <!-- crop select region -->
            <div class="view show-if-value acf-soh">
                <ul class="acf-hl acf-soh-target">
                    <li><a class="acf-icon -pencil dark" data-name="edit" href="#"><i class="acf-sprite-edit"></i></a></li>
                    <li><a class="acf-icon -cancel dark" data-name="remove" href="#"><i class="acf-sprite-delete"></i></a></li>
                </ul>
                <img data-name="image" src="<?php echo $url; ?>" alt=""/>
                <div class="crop-section">
                    <div class="crop-stage">
                        <div class="crop-action">
                            <h4><?php _e('Crop the image','acf-manual_image_crop'); ?></h4>
                            <?php if ($imageData->original_image ): ?>
                                <img class="crop-image" src="<?php echo $imageData->original_image_url ?>" data-width="<?php echo $imageData->original_image_width ?>" data-height="<?php echo $imageData->original_image_height ?>" alt="">
                            <?php endif ?>
                        </div>
                        <div class="crop-preview">
                            <h4><?php _e('Preview','acf-manual_image_crop'); ?></h4>
                            <div class="crop-controls">
                                <label for="crop_format">Format</label>
                                <select id="crop_format">
                                    <?php
                                    foreach(explode(" ", $field['image_formats']) as $format):
                                        echo '<option value="'.$format.'">'.$format.'</option>';
                                    endforeach;
                                    ?>
                                </select>
                                <br><br>
                                <a href="#" class="button button-large cancel-crop-button"><?php _e('Cancel','acf-manual_image_crop'); ?></a> <a href="#" class="button button-large button-primary perform-crop-button"><span class="dashicons dashicons-image-crop"></span> <?php _e('Crop!','acf-manual_image_crop'); ?></a>
                            </div>
                            <br>
                            <div class="preview"></div>
                        </div>
                    </div>
                    <a href="#" class="button button-large init-crop-button"><span class="dashicons dashicons-image-crop"></span> <?php _e('Crop','acf-manual_image_crop'); ?></a>
                </div>
            </div>

            <!-- start: add image -->

            <div class="view hide-if-value">
                <p><?php _e('No image selected','acf'); ?> <a data-name="add" class="acf-button" href="#"><?php _e('Add Image','acf'); ?></a></p>
            </div>

        </div>
    <?php

    }

    // helper function
    function get_image_data($field){
        $imageData = new stdClass();
        $imageData->original_image = '';
        $imageData->original_image_width = '';
        $imageData->original_image_height = '';
        $imageData->cropped_image = '';
        $imageData->original_image_url = '';
        $imageData->preview_image_url = '';
        $imageData->image_url = '';

        if($field['value'] == ''){
            // Field has not yet been saved or is an empty image field
            return $imageData;
        }

        /*
         * ORIGINAL IMAGE
         * .original_image  ID of original image
         * .original_image_width
         * .original_image_height
         * .original_image_url
         *
         * CROPPED IMAGE (ONLY STORED BY URL)
         * .image_url
         * .preview_image_url
         * .cropped_image (object)
         */

        $data = json_decode($field['value']);

        if(! is_object($data)){
            // Field was saved as a regular image field
            return $imageData;
        }

        if( !is_numeric($data->original_image) )
        {
            // The field has been saved, but has no image
            return $imageData;
        }

        // By now, we have at least a saved original image
        $imageAtts = wp_get_attachment_image_src($data->original_image, 'full');
        $imageData->original_image = $data->original_image;
        $imageData->original_image_width = $imageAtts[1];
        $imageData->original_image_height = $imageAtts[2];
        $imageData->original_image_url = $this->get_image_src($data->original_image, 'full');

        // Set defaults to original image
        $imageData->image_url = $this->get_image_src($data->original_image, 'full');
        $imageData->preview_image_url = $this->get_image_src($data->original_image, $field['preview_size']);

        // Check if there is a cropped version and set appropriate attributes
        if(is_object($data->cropped_image)){
            // Cropped image was not saved to media library and is only stored by its URL
            $imageData->cropped_image = $data->cropped_image;

            // Generate appropriate URLs
            $mediaDir = wp_upload_dir();
            $imageData->image_url = $mediaDir['baseurl'] . '/' .  $data->cropped_image->image;
            //$imageData->preview_image_url = $mediaDir['baseurl'] . '/' . $data->cropped_image->preview;
        }
        return $imageData;
    }

    function generate_cropped_image($id, $x1, $x2, $y1, $y2, $targetW, $targetH, $saveToMediaLibrary = false, $previewSize, $compression_factor, $field_name){//$id, $x1, $x2, $y$, $y2, $targetW, $targetH){
        require_once ABSPATH . "/wp-admin/includes/file.php";
        require_once ABSPATH . "/wp-admin/includes/image.php";

        $field_name = sanitize_file_name($field_name);

        // Create the variable that will hold the new image data
        $imageData = array();

        // Fetch media library info
        $mediaDir = wp_upload_dir();

        // Get original image info
        $originalImageData = wp_get_attachment_metadata($id);

        // Get image editor from original image path to crop the image
        $image = wp_get_image_editor( $mediaDir['basedir'] . '/' . $originalImageData['file'] );

        // Set quality
        $image->set_quality( apply_filters('acf-manual-image-crop/image-quality', $compression_factor) );

        if(! is_wp_error( $image ) ){

            // Crop the image using the provided measurements
            $image->crop($x1, $y1, $x2 - $x1, $y2 - $y1, $targetW, $targetH);

            // Retrieve original filename and separate it from its file extension
            $originalFileName = explode('.', basename($originalImageData['file']));

            // Retrieve and remove file extension from array
            $originalFileExtension = array_pop($originalFileName);

            // Generate new base filename
            $targetFileName = implode('.', $originalFileName) . '_cropped_'. $field_name  . '.' . $originalFileExtension;

            if(file_exists($mediaDir['path'] . '/' .$targetFileName)){
                unlink($mediaDir['path'] . '/' .$targetFileName);
            }

            // Generate target path new file using existing media library
            $targetFilePath = $mediaDir['path'] . '/' . wp_unique_filename( $mediaDir['path'], $targetFileName);

            // Get the relative path to save as the actual image url
            $targetRelativePath = str_replace($mediaDir['basedir'] . '/', '', $targetFilePath);

            // Save the image to the target path
            if(is_wp_error($image->save($targetFilePath))){
                // There was an error saving the image
                //TODO handle it
            }



            // Else we need to return the actual path of the cropped image

                // Add the image url to the imageData-array
                $imageData['value'] = array('image' => $targetRelativePath);
                $imageData['url'] = $mediaDir['baseurl'] . '/' . $targetRelativePath;

                // Get preview size dimensions
                global $_wp_additional_image_sizes;
                $previewWidth = 0;
                $previewHeight = 0;
                $crop = 0;
                if (isset($_wp_additional_image_sizes[$previewSize])) {
                    $previewWidth = intval($_wp_additional_image_sizes[$previewSize]['width']);
                    $previewHeight = intval($_wp_additional_image_sizes[$previewSize]['height']);
                    $crop = $_wp_additional_image_sizes[$previewSize]['crop'];
                } else {
                    $previewWidth = get_option($previewSize.'_size_w');
                    $previewHeight = get_option($previewSize.'_size_h');
                    $crop = get_option($previewSize.'_crop');
                }

                // Generate preview file path

                if(file_exists($mediaDir['path'] . '/preview_' . $targetFileName)){
                    unlink($mediaDir['path'] . '/preview_' . $targetFileName);
                }

                $previewFilePath = $mediaDir['path'] . '/' . wp_unique_filename( $mediaDir['path'], 'preview_' . $targetFileName);
                $previewRelativePath = str_replace($mediaDir['basedir'] . '/', '', $previewFilePath);

                // Get image editor from cropped image
                $croppedImage = wp_get_image_editor( $targetFilePath );
                $croppedImage->resize($previewWidth, $previewHeight, $crop);

                // Save the preview
                $croppedImage->save($previewFilePath);

                // Add the preview url
                $imageData['preview_url'] = $mediaDir['baseurl'] . '/' . $previewRelativePath;
                $imageData['value']['preview'] = $previewRelativePath;


            $imageData['success'] = true;
            return $imageData;
        }
        else{
            // Handle WP_ERROR
            $response = array();
            $response['success'] = false;
            $response['error_message'] = '';
            foreach($image->error_data as $code => $message){
                $response['error_message'] .= '<p><strong>' . $code . '</strong>: ' . $message . '</p>';
            }
            return $response;
        }
    }

    function get_image_src($id, $size = 'thumbnail'){
        $atts = wp_get_attachment_image_src( $id, $size);
        return $atts[0];
    }

    // settings
    function render_field_settings( $field ) {

        /*
        *  acf_render_field_setting
        *
        *  This function will create a setting for your field. Simply pass the $field parameter and an array of field settings.
        *  The array of settings does not require a `value` or `prefix`; These settings are found from the $field array.
        *
        *  More than one setting can be added by copy/paste the above code.
        *  Please note that you must also have a matching $defaults value for the field name (font_size)
        */

        // defaults
        $field = array_merge($this->defaults, $field);

        // compression
        acf_render_field_setting( $field, array(
            'label'         => __('Image compression factor','acf-manual_image_crop'),
            'instructions'  => __('Select a compression factor in the range 1-100','acf-manual_image_crop'),
            'type'          => 'number',
            'name'          => 'compression_factor'
        ));

        // image format
        acf_render_field_setting( $field, array(
            'label'         => __('Selectable image formats','acf-manual_image_crop'),
            'instructions'  => 'select the image formats (separated by whitespace). E.g.: 4:3 auto 3:2',
            'type'          => 'text',
            'name'          => 'image_formats'
        ));

        // fixed dimension
        acf_render_field_setting( $field, array(
            'label'			=> __('Fixed dimension','acf-manual_image_crop'),
            'instructions'	=> __('Select the dimension which should get fixed','acf-manual_image_crop'),
            'type'			=> 'radio',
            'name'			=> 'fixed_dimension',
            'choices'		=> array(
                'width'		=> __("Width",'acf-manual_image_crop'),
                'height'	=> __("Height",'acf-manual_image_crop'),
            ),
            'layout'	=>	'horizontal'
        ));

        // size
        acf_render_field_setting( $field, array(
            'label'         => __('Fixed dimension size','acf-manual_image_crop'),
            'instructions'  => __('Select the size of the fixed dimension in pixels','acf-manual_image_crop'),
            'type'          => 'number',
            'name'          => 'fixed_size'
        ));

        // preview_size
        acf_render_field_setting( $field, array(
            'label'         => __('Preview Size','acf'),
            'instructions'  => __('Shown when entering data','acf'),
            'type'          => 'select',
            'name'          => 'preview_size',
            'choices'       =>  acf_get_image_sizes()
        ));

    }


    // field (settings) handling
    function load_field( $field ) {

        // compose into text
        if(is_array($field['image_formats'])){
            $field['image_formats'] = $this->array2string($field['image_formats']);
        }

        return $field;

    }
    function cleanFormats($string){

    }
    function string2array($str){
        $formats = explode(' ', $str);
        $formats_clean = array();
        foreach($formats as $k=>$f){
            if($f == 'auto'){
                $formats_clean[] = 'auto';
            }elseif(count(explode(':', $f))==2){
                $format = explode(':', $f);
                array_walk($format, 'intval');
                $formats_clean[] = $format;
            }
        }
        return $formats_clean;
    }
    function array2string(array $arr){
        foreach($arr as $k=>$f){
            if(is_array($f)){
                // implode formats
                $arr[$k] = implode(':', $f);
            }
        }
        $out = implode(' ', $arr);

        // implode whole string
        return $out;
    }

    // field value handling

    function format_value( $value, $post_id, $field ) {

        // validate
        if( !$value ){
            return false;
        }

        $data = json_decode($value);

        if(is_object($data)){
            $value = $data->cropped_image;
        }
        else{
            // We are migrating from a standard image field
            $data = new stdClass();
            $data->cropped_image = $value;
            $data->original_image = $value;
        }

        // format
        if(is_array($data->cropped_image)){

            $value = $this->getAbsoluteImageUrl($data->cropped_image['image']);
        }
        elseif(is_object($data->cropped_image)){
            $value = $this->getAbsoluteImageUrl($data->cropped_image->image);
        }

        return $value;

    }

    function getAbsoluteImageUrl($relativeUrl){
        $mediaDir = wp_upload_dir();
        return $mediaDir['baseurl'] . '/' .  $relativeUrl;
    }

}

// create field
new acf_field_manual_image_crop();

endif;

?>