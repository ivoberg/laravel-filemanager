<?php

namespace UniSharp\LaravelFilemanager\Controllers;

use UniSharp\LaravelFilemanager\Events\FileIsMoving;
use UniSharp\LaravelFilemanager\Events\FileWasMoving;
use UniSharp\LaravelFilemanager\Events\FolderIsMoving;
use UniSharp\LaravelFilemanager\Events\FolderWasMoving;

class SearchController extends LfmController
{
    private $columns = ['icon', 'folder_path', 'name', 'time', 'size'];
    /**
     * Get the images to load for a selected folder.
     *
     * @return mixed
     */
    public function search()
    {
        $limit = $this->helper->input('limit');
        $offset = $this->helper->input('offset');
        $searchText = $this->helper->input('search');

        $files = $this->lfm->search($searchText, $limit, $offset);
        $items = collect($files)->map(function ($item) {
            return $item->fill()->attributes;
        })->toArray();
        return [
            'limit'     => $limit,
            'offset'     => $offset,
            'files_count'     => count($files),
            'items' => array_values($items),
            'columns' => $this->columns,
            'display' => $this->helper->getDisplayMode(),
            'working_dir' => $this->lfm->path('working_dir'),
        ];
    }

}
