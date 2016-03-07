<?php

namespace App\Model;

class MoviesModel extends BaseModel
{

    /** @var string */
    protected $table = 'movies';

    /** property */

    public $id;
    public $name;
    public $lan_path;
    public $dir_content;
    public $custom_name;
    public $csfd_title;
    public $csfd_poster;
    public $csfd_id;
    public $csfd_link;
    public $csfd_ranking;
    public $ranking;

    /** end property */

}