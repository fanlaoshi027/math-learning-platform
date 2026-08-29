<?php

defined('ABSPATH') || exit;

function mc_theme_assets(){
    wp_enqueue_style(
        'mc-theme-style',
        get_stylesheet_uri(),
        array(),
        '0.1.0'
    );
}
add_action('wp_enqueue_scripts','mc_theme_assets');

function mc_theme_setup(){
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
}
add_action('after_setup_theme','mc_theme_setup');
