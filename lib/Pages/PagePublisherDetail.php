<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

namespace SebLucas\Cops\Pages;

use SebLucas\Cops\Calibre\BookList;
use SebLucas\Cops\Calibre\Publisher;

class PagePublisherDetail extends Page
{
    protected string $className = Publisher::class;

    /**
     * Summary of InitializeContent
     * @return void
     */
    public function InitializeContent()
    {
        /** @var Publisher $instance */
        $instance = Publisher::getInstanceById($this->idGet, $this->getDatabaseId());
        if ($this->request->get('filter')) {
            $this->filterUri = '&p=' . $this->idGet;
            $this->getFilters($instance);
        } else {
            $this->getEntries($instance);
        }
        $this->idPage = $instance->getEntryId();
        $this->title = $instance->getTitle();
        $this->currentUri = $instance->getUri();
        $this->parentTitle = $instance->getParentTitle();
        $this->parentUri = $instance->getParentUri();
    }

    /**
     * Summary of getEntries
     * @param Publisher $instance
     * @return void
     */
    public function getEntries($instance = null)
    {
        $booklist = new BookList($this->request);
        [$this->entryArray, $this->totalNumber] = $booklist->getBooksByInstance($instance, $this->n);
        $this->sorted = $booklist->orderBy ?? "sort";
    }
}
