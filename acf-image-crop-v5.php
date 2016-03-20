<?php

if( ! class_exists('acf_field_image_crop') ) :
class acf_field_image_crop extends acf_field_image {


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

        $this->name = 'image_crop';


        /*
        *  label (string) Multiple words, can include spaces, visible when selecting a field type
        */

        $this->label = __('Image with user-crop', 'acf-image_crop');


        /*
        *  category (string) basic | content | choice | relational | jquery | layout | CUSTOM GROUP NAME
        */

        $this->category = 'content';


        /*
        *  defaults (array) Array of default settings which are merged into the field object. These are used later in settings
        */

        $this->defaults = array(
            'force_crop' => 'no',
            'crop_type' => 'hard',
            'preview_size' => 'medium',
            'save_format' => 'id',
            'save_in_media_library' => 'yes',
            'target_size' => 'thumbnail',
            'library' => 'all',
            'retina_mode' => 'no'
        );

        $this->options = get_option( 'acf_image_crop_settings' );

        // add ajax action to be able to retrieve full image size via javascript
        add_action( 'wp_ajax_acf_image_crop_get_image_size', array( &$this, 'crop_get_image_size' ) );
        add_action( 'wp_ajax_acf_image_crop_perform_crop', array( &$this, 'perform_crop' ) );


        // add filter to media query function to hide cropped images from media library
        add_filter('ajax_query_attachments_args', array($this, 'filterMediaQuery'));

        /*
        *  l10n (array) Array of strings that are used in JavaScript. This allows JS strings to be translated in PHP and loaded via:
        *  var message = acf._e('image_crop', 'error');
        */

        $this->l10n = array(
            'width_should_be'   => __( 'Width should be at least: ','acf-image_crop' ),
            'height_should_be'  => __( 'Height should be at least: ','acf-image_crop' ),
            'selected_width'    => __( 'Selected image width: ','acf-image_crop' ),
            'selected_height'   => __( 'Selected image height: ','acf-image_crop' ),
            'size_warning'      => __( 'Warning: The selected image is smaller than the required size!','acf-image_crop' ),
            'crop_error'        => __( 'Sorry, an error occurred when trying to crop your image:')
        );


        // do not delete!
        acf_field::__construct();
        //parent::__construct();

    }


    // AJAX handler for retieving full image dimensions from ID
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


    /*
    *  render_field()
    *
    *  Create the HTML interface for your field
    *
    *  @param   $field (array) the $field being rendered
    *
    *  @type    action
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $field (array) the $field being edited
    *  @return  n/a
    */

    function render_field( $field ) {


        // enqueue
        acf_enqueue_uploader();

        // get data from value
        //$data = json_decode($field['value']);
        $imageData = $this->get_image_data($field);

        $url = '';
        $orignialImage = null;

        if($imageData->original_image){
            $originalImage = wp_get_attachment_image_src($imageData->original_image, 'full');
            $url = $imageData->preview_image_url;
        }

        $width = 0;
        $height = 0;

        if($field['target_size'] == 'custom'){
            $width = $field['width'];
            $height = $field['height'];
        }
        else{
            global $_wp_additional_image_sizes;
            $s = $field['target_size'];
            if (isset($_wp_additional_image_sizes[$s])) {
                $width = intval($_wp_additional_image_sizes[$s]['width']);
                $height = intval($_wp_additional_image_sizes[$s]['height']);
            } else {
                $width = get_option($s.'_size_w');
                $height = get_option($s.'_size_h');
            }
        }

        // Retina mode
        if($this->getOption('retina_mode') || $field['retina_mode'] == 'yes'){
            $width = $width * 2;
            $height = $height * 2;
        }

        // vars
        $div_atts = array(
            'class'                 => 'acf-image-uploader acf-cf acf-image-crop',
            'data-crop_type'        => $field['crop_type'],
            'data-target_size'      => $field['target_size'],
            'data-width'            => $width,
            'data-height'           => $height,
            'data-force_crop'       => $field['force_crop'] == 'yes' ? 'true' : 'false',
            'data-save_to_media_library' => $field['save_in_media_library'],
            'data-save_format'      => $field['save_format'],
            'data-preview_size'     => $field['preview_size'],
            'data-library'          => $field['library']
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

        // has value?
        if($imageData->original_image){
            $url = $imageData->preview_image_url;
            $div_atts['class'] .= ' has-value';
        }

?>
<div <?php acf_esc_attr_e( $div_atts ); ?>>
    <div class="acf-hidden">
        <input <?php acf_esc_attr_e( $input_atts ); ?>/>
    </div>
    <div class="view show-if-value acf-soh">
        <ul class="acf-hl acf-soh-target">
            <li><a class="acf-icon -pencil dark" data-name="edit" href="#"><i class="acf-sprite-edit"></i></a></li>
            <li><a class="acf-icon -cancel dark" data-name="remove" href="#"><i class="acf-sprite-delete"></i></a></li>
        </ul>
        <img data-name="image" src="<?php echo $url; ?>" alt=""/>
        <div class="crop-section">
            <div class="crop-stage">
                <div class="crop-action">
                    <h4><?php _e('Crop the image','acf-image_crop'); ?></h4>
                <?php if ($imageData->original_image ): ?>
                    <img class="crop-image" src="<?php echo $imageData->original_image_url ?>" data-width="<?php echo $imageData->original_image_width ?>" data-height="<?php echo $imageData->original_image_height ?>" alt="">
                <?php endif ?>
                </div>
                <div class="crop-preview">
                    <h4><?php _e('Preview','acf-image_crop'); ?></h4>
                    <div class="preview"></div>
                    <div class="crop-controls">
                        <a href="#" class="button button-large cancel-crop-button"><?php _e('Cancel','acf-image_crop'); ?></a> <a href="#" class="button button-large button-primary perform-crop-button"><?php _e('Crop!','acf-image_crop'); ?></a>
                    </div>
                </div>
            </div>
            <a href="#" class="button button-large init-crop-button"><?php _e('Crop','acf-image_crop'); ?></a>
        </div>
    </div>
    <div class="view hide-if-value">
        <p><?php _e('No image selected','acf'); ?> <a data-name="add" class="acf-button" href="#"><?php _e('Add Image','acf'); ?></a></p>
    </div>
</div>
<?php

    }


    /*
    *  input_admin_enqueue_scripts()
    *
    *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
    *  Use this action to add CSS + JavaScript to assist your render_field() action.
    *
    *  @type    action (admin_enqueue_scripts)
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   n/a
    *  @return  n/a
    */



    function input_admin_enqueue_scripts() {
        $dir = plugin_dir_url( __FILE__ );


        // // register & include JS
        // wp_register_script( 'acf-input-image_crop', "{$dir}js/input.js" );
        // wp_enqueue_script('acf-input-image_crop');


        // // register & include CSS
        // wp_register_style( 'acf-input-image_crop', "{$dir}css/input.css" );
        // wp_enqueue_style('acf-input-image_crop');

        // register acf scripts
        //wp_register_script('acf-input-image', "{$dir}../advanced-custom-fields-pro/js/input/image.js");
        wp_register_script('acf-input-image_crop', "{$dir}js/input.js", array('acf-input', 'imgareaselect'));

        wp_register_style('acf-input-image_crop', "{$dir}css/input.css", array('acf-input'));

        // scripts
        wp_enqueue_script(array(
                'acf-input-image_crop'
        ));

        //wp_localize_script( 'acf-input-image_crop', 'ajax', array('nonce' => wp_create_nonce('acf_nonce')) );

        // styles
        wp_enqueue_style(array(
                'acf-input-image_crop',
                'imgareaselect'
        ));


    }

    /*
    *  input_admin_head()
    *
    *  This action is called in the admin_head action on the edit screen where your field is created.
    *  Use this action to add CSS and JavaScript to assist your render_field() action.
    *
    *  @type    action (admin_head)
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   n/a
    *  @return  n/a
    */

    /*

    function input_admin_head() {



    }

    */




    /*
    *  input_form_data()
    *
    *  This function is called once on the 'input' page between the head and footer
    *  There are 2 situations where ACF did not load during the 'acf/input_admin_enqueue_scripts' and
    *  'acf/input_admin_head' actions because ACF did not know it was going to be used. These situations are
    *  seen on comments / user edit forms on the front end. This function will always be called, and includes
    *  $args that related to the current screen such as $args['post_id']
    *
    *  @type    function
    *  @date    6/03/2014
    *  @since   5.0.0
    *
    *  @param   $args (array)
    *  @return  n/a
    */

    /*

    function input_form_data( $args ) {



    }

    */


    /*
    *  input_admin_footer()
    *
    *  This action is called in the admin_footer action on the edit screen where your field is created.
    *  Use this action to add CSS and JavaScript to assist your render_field() action.
    *
    *  @type    action (admin_footer)
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   n/a
    *  @return  n/a
    */

    /*

    function input_admin_footer() {



    }

    */


    /*
    *  field_group_admin_enqueue_scripts()
    *
    *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is edited.
    *  Use this action to add CSS + JavaScript to assist your render_field_options() action.
    *
    *  @type    action (admin_enqueue_scripts)
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   n/a
    *  @return  n/a
    */



    function field_group_admin_enqueue_scripts() {

        $dir = plugin_dir_url( __FILE__ );

        wp_register_script('acf-input-image-crop-options', "{$dir}js/options.js", array('jquery'));
        wp_enqueue_script( 'acf-input-image-crop-options');

        wp_register_style('acf-input-image-crop-options', "{$dir}css/options.css");
        wp_enqueue_style( 'acf-input-image-crop-options');
    }




    /*
    *  field_group_admin_head()
    *
    *  This action is called in the admin_head action on the edit screen where your field is edited.
    *  Use this action to add CSS and JavaScript to assist your render_field_options() action.
    *
    *  @type    action (admin_head)
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   n/a
    *  @return  n/a
    */

    /*

    function field_group_admin_head() {

    }

    */


    /*
    *  load_value()
    *
    *  This filter is applied to the $value after it is loaded from the db
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value (mixed) the value found in the database
    *  @param   $post_id (mixed) the $post_id from which the value was loaded
    *  @param   $field (array) the field array holding all the field options
    *  @return  $value
    */

    /*

    function load_value( $value, $post_id, $field ) {

        return $value;

    }

    */


    /*
    *  update_value()
    *
    *  This filter is applied to the $value before it is saved in the db
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value (mixed) the value found in the database
    *  @param   $post_id (mixed) the $post_id from which the value was loaded
    *  @param   $field (array) the field array holding all the field options
    *  @return  $value
    */

    /*

    function update_value( $value, $post_id, $field ) {

        return $value;

    }

    */


    /*
    *  format_value()
    *
    *  This filter is applied to the $value after it is loaded from the db and before it is returned to the template
    *
    *  @type    filter
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $value (mixed) the value which was loaded from the database
    *  @param   $post_id (mixed) the $post_id from which the value was loaded
    *  @param   $field (array) the field array holding all the field options
    *
    *  @return  $value (mixed) the modified value
    */



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
        if( $field['save_format'] == 'url' )
        {
            if(is_numeric($data->cropped_image)){
                $value = wp_get_attachment_url( $data->cropped_image );
            }
            elseif(is_array($data->cropped_image)){

                $value = $this->getAbsoluteImageUrl($data->cropped_image['image']);
            }
            elseif(is_object($data->cropped_image)){
                $value = $this->getAbsoluteImageUrl($data->cropped_image->image);
            }

        }
        elseif( $field['save_format'] == 'object' )
        {
            if(is_numeric($data->cropped_image )){
                $value = $this->getImageArray($data->cropped_image);
                $value['original_image'] = $this->getImageArray($data->original_image);
                // $attachment = get_post( $data->cropped_image );
                // // validate
                // if( !$attachment )
                // {
                //     return false;
                // }


                // // create array to hold value data
                // $src = wp_get_attachment_image_src( $attachment->ID, 'full' );

                // $value = array(
                //     'id' => $attachment->ID,
                //     'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                //     'title' => $attachment->post_title,
                //     'caption' => $attachment->post_excerpt,
                //     'description' => $attachment->post_content,
                //     'mime_type' => $attachment->post_mime_type,
                //     'url' => $src[0],
                //     'width' => $src[1],
                //     'height' => $src[2],
                //     'sizes' => array(),
                // );


                // // find all image sizes
                // $image_sizes = get_intermediate_image_sizes();

                // if( $image_sizes )
                // {
                //     foreach( $image_sizes as $image_size )
                //     {
                //         // find src
                //         $src = wp_get_attachment_image_src( $attachment->ID, $image_size );

                //         // add src
                //         $value[ 'sizes' ][ $image_size ] = $src[0];
                //         $value[ 'sizes' ][ $image_size . '-width' ] = $src[1];
                //         $value[ 'sizes' ][ $image_size . '-height' ] = $src[2];
                //     }
                //     // foreach( $image_sizes as $image_size )
                // }
            }
            elseif(is_array( $data->cropped_image) || is_object($data->cropped_image)){
                // Cropped image is not saved to media directory. Get data from original image instead
                $value = $this->getImageArray($data->original_image);

                // Get the relative url from data
                $relativeUrl  = '';
                if(is_array( $data->cropped_image)){
                    $relativeUrl = $data->cropped_image['image'];
                }
                else{
                    $relativeUrl = $data->cropped_image->image;
                }

                // Replace URL with cropped version
                $value['url'] = $this->getAbsoluteImageUrl($relativeUrl);

                // Calculate and replace sizes
                $imagePath = $this->getImagePath($relativeUrl);
                $dimensions = getimagesize($imagePath);
                $value['width'] = $dimensions[0];
                $value['height'] = $dimensions[1];

                // Add original image info
                $value['original_image'] = $this->getImageArray($data->original_image);
            }
            // elseif(is_object($data->cropped_image)){
            //     $value = $this->getImageArray($data->original_image);
            //     $value['url'] = $this->getAbsoluteImageUrl($data->cropped_image->image);

            //     // Calculate sizes
            //     $imagePath = $this->getImagePath($data->cropped_image->image);
            //     $dimensions = getimagesize($imagePath);
            //     $value['width'] = $dimensions[0];
            //     $value['height'] = $dimensions[1];

            //     // Add original image info
            //     $value['original_image'] = $this->getImageArray($data->original_image);
            // }
            else{
                //echo 'ELSE';
            }

        }
        return $value;

    }

    function getOption($key){
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }




    /*
    *  validate_value()
    *
    *  This filter is used to perform validation on the value prior to saving.
    *  All values are validated regardless of the field's required setting. This allows you to validate and return
    *  messages to the user if the value is not correct
    *
    *  @type    filter
    *  @date    11/02/2014
    *  @since   5.0.0
    *
    *  @param   $valid (boolean) validation status based on the value and the field's required setting
    *  @param   $value (mixed) the $_POST value
    *  @param   $field (array) the field array holding all the field options
    *  @param   $input (string) the corresponding input name for $_POST value
    *  @return  $valid
    */

    /*

    function validate_value( $valid, $value, $field, $input ){

        // Basic usage
        if( $value < $field['custom_minimum_setting'] )
        {
            $valid = false;
        }


        // Advanced usage
        if( $value < $field['custom_minimum_setting'] )
        {
            $valid = __('The value is too little!','acf-image_crop'),
        }


        // return
        return $valid;

    }

    */


    /*
    *  delete_value()
    *
    *  This action is fired after a value has been deleted from the db.
    *  Please note that saving a blank value is treated as an update, not a delete
    *
    *  @type    action
    *  @date    6/03/2014
    *  @since   5.0.0
    *
    *  @param   $post_id (mixed) the $post_id from which the value was deleted
    *  @param   $key (string) the $meta_key which the value was deleted
    *  @return  n/a
    */

    /*

    function delete_value( $post_id, $key ) {



    }

    */



    /*
    *  render_field_settings()
    *
    *  Create extra settings for your field. These are visible when editing a field
    *
    *  @type    action
    *  @since   3.6
    *  @date    23/01/13
    *
    *  @param   $field (array) the $field being edited
    *  @return  n/a
    */

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

        // compression
        acf_render_field_setting( $field, array(
            'label'         => __('Image compression factor','acf-image_crop'),
            'instructions'  => __('Select a compression factor in the range 1-100','acf-image_crop'),
            'type'          => 'number',
            'name'          => 'compression_factor'
        ));

        // image format
        acf_render_field_setting( $field, array(
            'label'         => __('Selectable image formats','acf-image_crop'),
            'instructions'  => 'select the image formats',
            'type'          => 'textarea',
            'name'          => 'image_formats'
        ));

        // fixed dimension
        acf_render_field_setting( $field, array(
            'label'			=> __('Fixed dimension','acf-image_crop'),
            'instructions'	=> __('Select the dimension which should get fixed','acf-image_crop'),
            'type'			=> 'radio',
            'name'			=> 'fixed_dimension',
            'choices'		=> array(
                'width'		=> __("Width",'acf-image_crop'),
                'height'	=> __("Height",'acf-image_crop'),
            ),
            'layout'	=>	'horizontal'
        ));

        // size
        acf_render_field_setting( $field, array(
            'label'         => __('Fixed dimension size','acf-image_crop'),
            'instructions'  => __('Select the size of the fixed dimension in pixels','acf-image_crop'),
            'type'          => 'number',
            'name'          => 'fixed_size'
        ));

    }


    /*
    *  load_field()
    *
    *  This filter is applied to the $field after it is loaded from the database
    *
    *  @type    filter
    *  @date    23/01/2013
    *  @since   3.6.0
    *
    *  @param   $field (array) the field array holding all the field options
    *  @return  $field
    */

    /*

    function load_field( $field ) {

        return $field;

    }

    */


    /*
    *  update_field()
    *
    *  This filter is applied to the $field before it is saved to the database
    *
    *  @type    filter
    *  @date    23/01/2013
    *  @since   3.6.0
    *
    *  @param   $field (array) the field array holding all the field options
    *  @return  $field
    */


    function update_field( $field ) {

        // compression factor (range 1-100)
        $field['compression_factor'] = intval($field['compression_factor']);
        if($field['compression_factor']<0){
            $field['compression_factor'] = 1;
        }elseif($field['compression_factor']>100){
            $field['compression_factor'] = 100;
        }

        // parse cropping formats

        $formats = preg_split('/\R/', $field['image_formats']);
        $formats_clean = array();

        foreach($formats as $k=>$f){
            if(count(explode(':', $f))==2){
                $format = explode(':', $f);
                array_walk($format, 'intval');
                $formats_clean[] = $format;
            }
        }

        $field['image_formats'] = $formats_clean;
        var_dump($formats);
        var_dump($formats_clean);
        die();

        return $field;

    }


    /*
    *  delete_field()
    *
    *  This action is fired after a field is deleted from the database
    *
    *  @type    action
    *  @date    11/02/2014
    *  @since   5.0.0
    *
    *  @param   $field (array) the field array holding all the field options
    *  @return  n/a
    */

    /*

    function delete_field( $field ) {



    }

    */


}


// create field
new acf_field_image_crop();

endif;

?>