<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

namespace SebLucas\Cops\Pages;

use SebLucas\Cops\Calibre\Book;
use SebLucas\Cops\Calibre\BookList;

class PageAllBooks extends Page
{
    public function InitializeContent()
    {
        $this->getEntries();
        $this->idPage = Book::PAGE_ID;
        $this->title = localize("allbooks.title");
    }

    public function getEntries()
    {
        $booklist = new BookList($this->request);
        if ($this->request->option("titles_split_first_letter") == 1) {
            $this->entryArray = $booklist->getCountByFirstLetter();
        } elseif (!empty($this->request->option("titles_split_publication_year"))) {
            $this->entryArray = $booklist->getCountByPubYear();
        } else {
            [$this->entryArray, $this->totalNumber] = $booklist->getAllBooks($this->n);
        }
    }
}