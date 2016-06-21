<?php

namespace App\Presenters;

use Nette;
use App\Model;
use App\Model\DirsModel;
use App\Model\MoviesModel;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Finder;


class MediaCentrumPresenter extends BasePresenter
{
    /**
     * @var DirsModel
     * @inject
     */
    public $dirsModel;
    /**
     * @var MoviesModel
     * @inject
     */
    public $moviesModel;

    private $movieId;
    private $movies;
    private $playlist;
    private $vlcStatus;
    private $showRating = [];
    private $showRename = [];

    public function actionIndex()
    {
        $this->vlcStatus = $this->getVlcStatus();
        $this->playlist = $this->getVlcPlaylist();
        $this->movies = $this->moviesModel->order('custom_name ASC')->fetchAll();
        $this->template->vlcStatus = $this->vlcStatus;
        $this->template->playlist = $this->playlist;
        $this->template->movies = $this->movies;
        $this->template->showRating = $this->showRating;
        $this->template->showRename = $this->showRename;
    }

    public function renderIndex()
    {
        $this->template->movies = $this->movies;
        $this->template->showRating = $this->showRating;
        $this->template->showRename = $this->showRename;
    }

    public function createComponentRankingForm()
    {
        $ranks = [
            0 => '0%',
            5 => '5%',
            10 => '10%',
            15 => '15%',
            20 => '20%',
            25 => '25%',
            30 => '30%',
            35 => '35%',
            40 => '40%',
            45 => '45%',
            50 => '50%',
            55 => '55%',
            60 => '60%',
            65 => '65%',
            70 => '70%',
            75 => '75%',
            80 => '80%',
            85 => '85%',
            90 => '90%',
            95 => '95%',
            100 => '100%'
        ];
        /** @var MoviesModel $movie */
        $movie = $this->moviesModel->get($this->movieId);
        $form = new Form();
        if (isset($movie->ranking)) {
            $form->addSelect('ranking', 'Hodnocení', $ranks)->setDefaultValue($movie->ranking);
        } else {
            $form->addSelect('ranking', 'Hodnocení', $ranks);
        }
        $form->addHidden('movieId');
        $form->addSubmit('submit', 'OK');
        $form->onSubmit[] = [$this, 'processRankingForm'];
        return $form;
    }

    public function createComponentRenameForm()
    {
        $form = new Form();
        $form->addText('custom_name', 'Název');
        $form->addHidden('movieId');
        $form->addSubmit('submit', 'OK');
        $form->onSubmit[] = [$this, 'processRenameForm'];
        return $form;
    }

    public function handleScanDirectories()
    {
        $dirs = $this->dirsModel->getAll();
        /** @var DirsModel $dir */
        foreach ($dirs as $dir) {
            /**
             * @var \SplFileInfo $d
             */
            foreach (Finder::findDirectories('*')->in($dir->dir) as $d) {
                $content = [];
                /** @var \SplFileInfo $file */
                foreach (Finder::findFiles('*')->from($d->getPathname()) as $file) {
                    $content[] = iconv(mb_detect_encoding($file->getPathname(), mb_detect_order(), true), "UTF-8", $file->getPathname());
                }

                $name = $d->getPathname();
                $name = str_replace($dir->dir, '', $name);
                $name = iconv(mb_detect_encoding($name, mb_detect_order(), true), "UTF-8", $name);
                if (!in_array($name, ['.', '..']) && substr($name, 0, 1) != "_") {
                    if ($this->moviesModel->where(['lan_path' => $dir->lan_path . $name])->count() === 0) {
                        $path = iconv(mb_detect_encoding($d->getPathname(), mb_detect_order(), true), "UTF-8", $d->getPathname());
                        $this->moviesModel->insert([
                            'lan_path' => $dir->lan_path . $name,
                            'dir_content' => serialize($content),
                            'name' => $path,
                            'custom_name' => $name
                        ]);
                    } else {
                        $this->moviesModel->where(['lan_path' => $dir->lan_path . $name])->update([
                            'dir_content' => serialize($content),
                        ]);
                    }
                }
            }
        }
        $this->redirect('index');
    }

    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir."/".$object))
                        $this->rrmdir($dir."/".$object);
                    else
                        unlink($dir."/".$object);
                }
            }
            rmdir($dir);
        }
    }

    public function handleClearCache()
    {
        $this->rrmdir(__DIR__ . '/../../temp/cache/');
        $this->terminate();
    }

    public function handleCheckCSFD($movieId)
    {
        $csfdSession = $this->getSession('csfd');
        /** @var MoviesModel $movie */
        $movie = $this->moviesModel->get($movieId);
        $baseUrl = 'http://csfdapi.cz/movie?';
        $url = $baseUrl . http_build_query(array(
                'search' => $movie->custom_name,
            ));
        $now = new DateTime();
        if (!$csfdSession->timeout) {
            $csfdSession->timeout = new DateTime();
        }
        $diff = $now->diff($csfdSession->timeout);
        if (($diff->m >= 0 && $diff->s >= 10) || ($diff->m >= 0 && $diff->s >= 0)) {
            /** @var \stdClass[] $movies */
            $movies = json_decode(file_get_contents($url));
            file_put_contents(__DIR__ . '/../../www/images/posters/' . $movie->id . '.jpg', file_get_contents($movies[0]->poster_url));
            $this->moviesModel->where(['id' => $movie->id])->update([
                'csfd_poster' => $movie->id . '.jpg',
                'csfd_id' => $movies[0]->id,
                'csfd_link' => $movies[0]->csfd_url,
                'csfd_title' => $movies[0]->names->cs,
                'csfd_year' => $movies[0]->year
            ]);

        } else {
            $this->flashMessage('Poslední dotaz odeslán jen před : ' . $diff->m . ":" . $diff->s . ' (Odeslat lze 1 požadavek za 10 sekund)', 'error');
        }
//        $this->redirect('index');
        $this->redrawControl();
    }

    public function handleAddRanking($movieId)
    {
        $this->movieId = $movieId;
        $this->showRating[$movieId] = true;
        $this->redrawControl();
    }

    public function handleRenameMovie($movieId)
    {
        $this->showRename[$movieId] = true;
        $this->redrawControl();
    }

    public function processRankingForm(Form $form)
    {
        $values = $form->getValues();
        $this->moviesModel->where([
            'id' => $values->movieId
        ])->update([
            'ranking' => $values->ranking
        ]);
        unset($this->showRating[$values->movieId]);
        $form->setValues([], true);
//        $this->redirect('index');
        $this->redrawControl();
    }

    public function processRenameForm(Form $form)
    {
        $values = $form->getValues();
        $this->moviesModel->where([
            'id' => $values->movieId
        ])->update([
            'custom_name' => $values->custom_name
        ]);
        unset($this->showRename[$values->movieId]);
        $form->setValues([], true);
//        $this->redirect('index');
        $this->redrawControl();
    }

    private function getCurl($command)
    {
        $user = '';
        $password = 'filip';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'http://192.168.0.15/requests/' . $command,
            CURLOPT_USERPWD => $user . ':' . $password,
            CURLOPT_USERAGENT => 'cURL Request'
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
        return $resp;
    }

    private function makeArrayFromXML($xml)
    {
        xml_parse_into_struct(xml_parser_create(), $xml, $array);
        return $array;
    }

    public function getVlcPlaylist()
    {
        $command = "playlist.xml";
        $xml = $this->getCurl($command);
        $playlistArray = $this->makeArrayFromXML($xml);
        $array = [];
        foreach ($playlistArray as $value) {
            if ($value['tag'] == 'LEAF') {
                $array[$value['attributes']['ID']] = $value['attributes']['NAME'];
            }
        }
        return $array;
    }

    public function handleSyncPlaylist()
    {
        $this->playlist = $this->getVlcPlaylist();
        $this->vlcStatus = $this->getVlcStatus();
        $this->template->vlcStatus = $this->vlcStatus;
        $this->template->playlist = $this->playlist;
        $this->redrawControl('vlcPlayer');
    }

    public function handleAddToVlcPlaylist($movieId)
    {
        /** @var MoviesModel|ActiveRow $movie */
        $movie = $this->moviesModel->get($movieId);
        $command = 'status.xml?command=in_enqueue&input=' . rawurlencode($movie->lan_path);
        $this->getCurl($command);
        $this->playlist = $this->getVlcPlaylist();
        $this->vlcStatus = $this->getVlcStatus();
        $this->template->vlcStatus = $this->vlcStatus;
        $this->template->playlist = $this->playlist;
        $this->redrawControl('vlcPlayer');
    }

    public function handleAddToVlcPlaylistAndPlay($movieId)
    {
        /** @var MoviesModel|ActiveRow $movie */
        $movie = $this->moviesModel->get($movieId);
        $command = 'status.xml?command=in_play&input=' . rawurlencode($movie->lan_path);
        $this->getCurl($command);
        $this->playlist = $this->getVlcPlaylist();
        $this->vlcStatus = $this->getVlcStatus();
        $this->template->vlcStatus = $this->vlcStatus;
        $this->template->playlist = $this->playlist;
        $this->redrawControl('vlcPlayer');
    }

    public function handleVlcPlayPause()
    {
        $command = 'status.xml?command=pl_pause';
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcStop()
    {
        $command = 'status.xml?command=pl_stop';
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcPrev()
    {
        $command = 'status.xml?command=pl_previous';
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcNext()
    {
        $command = 'status.xml?command=pl_next';
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcPlayItem($id)
    {
        $command = 'status.xml?command=pl_play&id=' . $id;
        $this->getCurl($command);
        sleep(1);
        $this->playlist = $this->getVlcPlaylist();
        $this->vlcStatus = $this->getVlcStatus();
        $this->template->vlcStatus = $this->vlcStatus;
        $this->template->playlist = $this->playlist;
        $this->redrawControl('vlcPlayer');
    }

    public function handleVlcDeleteItem($id)
    {
        $command = 'status.xml?command=pl_delete&id=' . $id;
        $this->getCurl($command);
        $this->playlist = $this->getVlcPlaylist();
        $this->vlcStatus = $this->getVlcStatus();
        $this->redrawControl('vlcPlayer');
    }

    public function handleVlcErasePlaylist()
    {
        $command = 'status.xml?command=pl_empty';
        $this->getCurl($command);
        $this->playlist = $this->getVlcPlaylist();
        $this->vlcStatus = $this->getVlcStatus();
        $this->template->vlcStatus = $this->vlcStatus;
        $this->template->playlist = $this->playlist;
        $this->redrawControl('vlcPlayer');
    }

    public function handleVlcFullscreen()
    {
        $command = 'status.xml?command=fullscreen';
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcMute()
    {
        $command = 'status.xml?command=volume&val=0';
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcUnmute()
    {
        $command = 'status.xml?command=volume&val=256';// . rawurlencode('+100%');
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcVolumeUp()
    {
        $command = 'status.xml?command=volume&val=+63.5';
        $this->getCurl($command);
        $this->terminate();
    }

    public function handleVlcVolumeDown()
    {
        $command = 'status.xml?command=volume&val=-63.5';
        $this->getCurl($command);
        $this->terminate();
    }

    public function getVlcStatus()
    {
        $command = 'status.xml';
        $xml = $this->getCurl($command);
        $state = [
            'state' => '',
            'playing' => [
                'id' => 0,
                'title' => ''
            ]
        ];
        foreach($this->makeArrayFromXML($xml) as $item) {
            if (isset($item['tag']) && $item['tag'] == 'STATE') {
                $state['state'] = $item['value'];
            }
            if (isset($item['tag']) && $item['tag'] == 'INFO' && isset($item['attributes']['NAME']) && $item['attributes']['NAME'] == 'title') {
                $state['playing']['title'] = $item['value'];
            }
            if (isset($item['tag']) && $item['tag'] == 'INFO' && isset($item['attributes']['NAME']) && $item['attributes']['NAME'] == 'filename') {
                $state['playing']['filename'] = $item['value'];
            }
            if (isset($item['tag']) && $item['tag'] == 'CURRENTPLID') {
                $state['playing']['id'] = $item['value'];
            }
        }
        return $state;
    }

}
