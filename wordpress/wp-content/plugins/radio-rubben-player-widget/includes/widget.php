<?php
namespace RadioRubben\PlayerWidget;

final class Widget extends \WP_Widget {
    public function __construct() { parent::__construct('rr_player_matches','Radio Rubben – spillerkamper',['description'=>'Kommende spillerkamper med MyGame-lenker.']); }
    public function widget($args,$instance) {
        echo $args['before_widget'];
        echo View::shortcode(['player'=>$instance['player']??0,'limit'=>$instance['limit']??1]);
        echo $args['after_widget'];
    }
    public function form($instance) {
        echo '<p><label for="'.esc_attr($this->get_field_id('player')).'">Spillerens NFF-ID (0 viser alle)</label><input class="widefat" type="number" min="0" name="'.esc_attr($this->get_field_name('player')).'" id="'.esc_attr($this->get_field_id('player')).'" value="'.absint($instance['player']??0).'"></p>';
        echo '<p><label for="'.esc_attr($this->get_field_id('limit')).'">Antall kampkort</label><input type="number" min="1" max="6" name="'.esc_attr($this->get_field_name('limit')).'" id="'.esc_attr($this->get_field_id('limit')).'" value="'.max(1,min(6,(int)($instance['limit']??1))).'"></p>';
    }
    public function update($new,$old) { return ['player'=>absint($new['player']??0),'limit'=>max(1,min(6,(int)($new['limit']??1)))]; }
}
