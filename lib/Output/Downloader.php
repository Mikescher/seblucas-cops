<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 * @author     mikespub
 */

namespace SebLucas\Cops\Output;

use SebLucas\Cops\Calibre\Author;
use SebLucas\Cops\Calibre\Serie;
use SebLucas\Cops\Input\Config;
use SebLucas\Cops\Input\Request;
use SebLucas\Cops\Pages\PageId;
use SebLucas\Cops\Pages\Page;
use ZipStream\ZipStream;

/**
 * Downloader for multiple books
 */
class Downloader
{
    public static string $endpoint = Config::ENDPOINT["download"];

    /** @var Request */
    protected $request;
    /** @var mixed */
    protected $databaseId = null;
    /** @var string */
    protected $format = 'EPUB';
    /** @var string */
    protected $fileName = 'download.epub.zip';
    /** @var array<string, string> */
    protected $fileList = [];

    /**
     * Summary of __construct
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->databaseId = $this->request->get('db');
        $type = $this->request->get('type', 'epub');
        $this->format = strtoupper($type);
    }

    /**
     * Summary of isValid
     * @return bool
     */
    public function isValid()
    {
        $entries = $this->hasPage();
        if (!$entries) {
            $entries = $this->hasSeries();
            if (!$entries) {
                $entries = $this->hasAuthor();
                if (!$entries) {
                    return false;
                }
            }
        }
        return $this->checkFileList($entries);
    }

    /**
     * Summary of hasPage
     * @return array<mixed>|bool
     */
    public function hasPage()
    {
        if (!in_array($this->format, Config::get('download_page'))) {
            return false;
        }
        $pageId = $this->request->get('page', null, '/^\d+$/');
        if (empty($pageId)) {
            return false;
        }
        /** @var Page $instance */
        $instance = PageId::getPage($pageId, $this->request);
        $instance->InitializeContent();
        if (empty($instance)) {
            return false;
        }
        if ($this->format == 'ANY') {
            $this->fileName = $instance->title . '.zip';
        } else {
            $this->fileName = $instance->title . '.' . strtolower($this->format) . '.zip';
        }
        if (!empty($instance->parentTitle)) {
            $this->fileName = $instance->parentTitle . ' - ' . $this->fileName;
        }
        $instance->InitializeContent();
        return $instance->entryArray;
    }

    /**
     * Summary of hasSeries
     * @return array<mixed>|bool
     */
    public function hasSeries()
    {
        if (!in_array($this->format, Config::get('download_series'))) {
            return false;
        }
        $seriesId = $this->request->get('series', null, '/^\d+$/');
        if (empty($seriesId)) {
            return false;
        }
        /** @var Serie $instance */
        $instance = Serie::getInstanceById($seriesId, $this->databaseId);
        if (empty($instance->id)) {
            return false;
        }
        if ($this->format == 'ANY') {
            $this->fileName = $instance->name . '.zip';
        } else {
            $this->fileName = $instance->name . '.' . strtolower($this->format) . '.zip';
        }
        $entries = $instance->getBooks();  // -1
        return $entries;
    }

    /**
     * Summary of hasAuthor
     * @return array<mixed>|bool
     */
    public function hasAuthor()
    {
        if (!in_array($this->format, Config::get('download_author'))) {
            return false;
        }
        $authorId = $this->request->get('author', null, '/^\d+$/');
        if (empty($authorId)) {
            return false;
        }
        /** @var Author $instance */
        $instance = Author::getInstanceById($authorId, $this->databaseId);
        if (empty($instance->id)) {
            return false;
        }
        if ($this->format == 'ANY') {
            $this->fileName = $instance->name . '.zip';
        } else {
            $this->fileName = $instance->name . '.' . strtolower($this->format) . '.zip';
        }
        $entries = $instance->getBooks();  // -1
        return $entries;
    }

    /**
     * Summary of checkFileList
     * @param array<mixed> $entries
     * @return bool
     */
    public function checkFileList($entries)
    {
        if (count($entries) < 1) {
            return false;
        }
        $this->fileList = [];
        if ($this->format == 'ANY') {
            $checkFormats = Config::get('prefered_format');
        } else {
            $checkFormats = [ $this->format ];
        }
        foreach ($entries as $entry) {
            $data = false;
            foreach ($checkFormats as $format) {
                $data = $entry->book->getDataFormat($format);
                if ($data) {
                    break;
                }
            }
            if (!$data) {
                continue;
            }
            $path = $data->getLocalPath();
            if (!file_exists($path)) {
                continue;
            }
            $name = basename($path);
            $this->fileList[$name] = $path;
        }
        if (count($this->fileList) < 1) {
            return false;
        }
        return true;
    }

    /**
     * Summary of download
     * @return void
     */
    public function download()
    {
        // keep it simple for now, and use the basic options
        $zip = new ZipStream(
            outputName: $this->fileName,
            sendHttpHeaders: true,
        );
        foreach ($this->fileList as $name => $path) {
            $zip->addFileFromPath(
                fileName: $name,
                path: $path,
            );
        }
        $zip->finish();
    }
}
