<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

namespace SebLucas\Cops\Calibre;

use SebLucas\Cops\Input\Config;
use SebLucas\Cops\Input\Route;
use SebLucas\Cops\Model\EntryBook;
use SebLucas\Cops\Model\LinkEntry;
use SebLucas\Cops\Model\LinkFeed;
use SebLucas\Cops\Output\Format;
use SebLucas\Cops\Pages\PageId;
use SebLucas\EPubMeta\EPub;
use SebLucas\EPubMeta\Tools\ZipEdit;
use Exception;

//class Book extends Base
class Book
{
    public const PAGE_ID = PageId::ALL_BOOKS_ID;
    public const PAGE_ALL = PageId::ALL_BOOKS;
    public const PAGE_LETTER = PageId::ALL_BOOKS_LETTER;
    public const PAGE_YEAR = PageId::ALL_BOOKS_YEAR;
    public const PAGE_DETAIL = PageId::BOOK_DETAIL;
    public const SQL_TABLE = "books";
    public const SQL_LINK_TABLE = "books";
    public const SQL_LINK_COLUMN = "id";
    public const SQL_SORT = "sort";
    public const SQL_COLUMNS = 'books.id as id, books.title as title, text as comment, path, timestamp, pubdate, series_index, uuid, has_cover, ratings.rating';
    public const SQL_ALL_ROWS = BookList::SQL_BOOKS_ALL;

    public const SQL_BOOKS_LEFT_JOIN = 'left outer join comments on comments.book = books.id
    left outer join books_ratings_link on books_ratings_link.book = books.id
    left outer join ratings on books_ratings_link.rating = ratings.id ';

    public const BAD_SEARCH = 'QQQQQ';

    public static string $endpoint = Config::ENDPOINT["index"];
    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var mixed */
    public $timestamp;
    /** @var mixed */
    public $pubdate;
    /** @var string */
    public $path;
    /** @var string */
    public $uuid;
    /** @var bool */
    public $hasCover;
    /** @var string */
    public $relativePath;
    /** @var ?float */
    public $seriesIndex;
    /** @var string */
    public $comment;
    /** @var ?int */
    public $rating;
    /** @var ?int */
    protected $databaseId = null;
    /** @var ?array<Data> */
    public $datas = null;
    /** @var ?array<Author> */
    public $authors = null;
    /** @var Publisher|false|null */
    public $publisher = null;
    /** @var Serie|false|null */
    public $serie = null;
    /** @var ?array<Tag> */
    public $tags = null;
    /** @var ?array<Identifier> */
    public $identifiers = null;
    /** @var ?string */
    public $languages = null;
    /** @var array<mixed> */
    public $format = [];
    /** @var ?string */
    protected $coverFileName = null;
    public bool $updateForKepub = false;

    /**
     * Summary of __construct
     * @param object $line
     * @param ?int $database
     */
    public function __construct($line, $database = null)
    {
        $this->id = $line->id;
        $this->title = $line->title;
        $this->timestamp = strtotime($line->timestamp);
        $this->pubdate = $line->pubdate;
        //$this->path = Database::getDbDirectory() . $line->path;
        //$this->relativePath = $line->path;
        // -DC- Init relative or full path
        $this->path = $line->path;
        if (!is_dir($this->path)) {
            $this->path = Database::getDbDirectory($database) . $line->path;
        }
        $this->seriesIndex = $line->series_index;
        $this->comment = $line->comment ?? '';
        $this->uuid = $line->uuid;
        $this->hasCover = $line->has_cover;
        $this->rating = $line->rating;
        $this->databaseId = $database;
        // do this at the end when all properties are set
        if ($this->hasCover) {
            $this->coverFileName = Cover::findCoverFileName($this, $line);
            if (empty($this->coverFileName)) {
                $this->hasCover = false;
            }
        }
    }

    /**
     * Summary of getDatabaseId
     * @return ?int
     */
    public function getDatabaseId()
    {
        return $this->databaseId;
    }

    /**
     * Summary of getCoverFileName
     * @return ?string
     */
    public function getCoverFileName()
    {
        if ($this->hasCover) {
            return $this->coverFileName;
        }
        return null;
    }

    /**
     * Summary of getEntryId
     * @return string
     */
    public function getEntryId()
    {
        return PageId::ALL_BOOKS_UUID.':'.$this->uuid;
    }

    /**
     * Summary of getEntryIdByLetter
     * @param string $startingLetter
     * @return string
     */
    public static function getEntryIdByLetter($startingLetter)
    {
        return static::PAGE_ID.':letter:'.$startingLetter;
    }

    /**
     * Summary of getEntryIdByYear
     * @param string|int $year
     * @return string
     */
    public static function getEntryIdByYear($year)
    {
        return static::PAGE_ID.':year:'.$year;
    }

    /**
     * Summary of getUri
     * @return string
     */
    public function getUri()
    {
        if (Config::get('use_route_urls')) {
            return Route::page(static::PAGE_DETAIL, ['id' => $this->id, 'author' => $this->getAuthorsName(), 'title' => $this->getTitle()]);
        }
        return Route::page(static::PAGE_DETAIL, ['id' => $this->id]);
    }

    /**
     * Summary of getDetailUrl
     * @param string|null $endpoint
     * @return string
     */
    public function getDetailUrl($endpoint = null)
    {
        $endpoint ??= static::$endpoint;
        if (Config::get('use_route_urls')) {
            return Route::url($endpoint, static::PAGE_DETAIL, ['id' => $this->id, 'author' => $this->getAuthorsName(), 'title' => $this->getTitle()]);
        }
        return Route::url($endpoint, static::PAGE_DETAIL, ['id' => $this->id, 'db' => $this->databaseId]);
    }

    /**
     * Summary of getTitle
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /* Other class (author, series, tag, ...) initialization and accessors */

    /**
     * @param int $n
     * @param ?string $sort
     * @return ?array<Author>
     */
    public function getAuthors($n = -1, $sort = null)
    {
        if (is_null($this->authors)) {
            $this->authors = Author::getInstancesByBookId($this->id, $this->databaseId);
        }
        return $this->authors;
    }

    /**
     * Summary of getAuthorsName
     * @return string
     */
    public function getAuthorsName()
    {
        return implode(', ', array_map(function ($author) {
            return $author->name;
        }, $this->getAuthors()));
    }

    /**
     * Summary of getAuthorsSort
     * @return string
     */
    public function getAuthorsSort()
    {
        return implode(', ', array_map(function ($author) {
            return $author->sort;
        }, $this->getAuthors()));
    }

    /**
     * Summary of getPublisher
     * @return Publisher|false
     */
    public function getPublisher()
    {
        if (is_null($this->publisher)) {
            $this->publisher = Publisher::getInstanceByBookId($this->id, $this->databaseId);
        }
        return $this->publisher;
    }

    /**
     * @return Serie|false
     */
    public function getSerie()
    {
        if (is_null($this->serie)) {
            $this->serie = Serie::getInstanceByBookId($this->id, $this->databaseId);
        }
        return $this->serie;
    }

    /**
     * @param int $n
     * @param ?string $sort
     * @return string
     */
    public function getLanguages($n = -1, $sort = null)
    {
        if (is_null($this->languages)) {
            $this->languages = Language::getLanguagesByBookId($this->id, $this->databaseId);
        }
        return $this->languages;
    }

    /**
     * @param int $n
     * @param ?string $sort
     * @return array<Tag>
     */
    public function getTags($n = -1, $sort = null)
    {
        if (is_null($this->tags)) {
            $this->tags = Tag::getInstancesByBookId($this->id, $this->databaseId);
        }
        return $this->tags;
    }

    /**
     * Summary of getTagsName
     * @return string
     */
    public function getTagsName()
    {
        return implode(', ', array_map(function ($tag) {
            return $tag->name;
        }, $this->getTags()));
    }

    /**
     * @return array<Identifier>
     */
    public function getIdentifiers()
    {
        if (is_null($this->identifiers)) {
            $this->identifiers = Identifier::getInstancesByBookId($this->id, $this->databaseId);
        }
        return $this->identifiers;
    }

    /**
     * @return array<Data>
     */
    public function getDatas()
    {
        if (is_null($this->datas)) {
            $this->datas = static::getDataByBook($this);
        }
        return $this->datas;
    }

    /**
     * Summary of GetMostInterestingDataToSendToKindle
     * @return ?Data
     */
    public function GetMostInterestingDataToSendToKindle()
    {
        $bestFormatForKindle = ['PDF', 'AZW3', 'MOBI', 'EPUB'];
        $bestRank = -1;
        $bestData = null;
        foreach ($this->getDatas() as $data) {
            $key = array_search($data->format, $bestFormatForKindle);
            if ($key !== false && $key > $bestRank) {
                $bestRank = $key;
                $bestData = $data;
            }
        }
        return $bestData;
    }

    /**
     * Summary of getDataById
     * @param int $idData
     * @return Data|false
     */
    public function getDataById($idData)
    {
        $reduced = array_filter($this->getDatas(), function ($data) use ($idData) {
            return $data->id == $idData;
        });
        return reset($reduced);
    }

    /**
     * Summary of getRating
     * @return string
     */
    public function getRating()
    {
        if (is_null($this->rating) || $this->rating == 0) {
            return '';
        }
        $retour = '';
        for ($i = 0; $i < $this->rating / 2; $i++) {
            $retour .= '&#9733;'; // full star
        }
        for ($i = 0; $i < 5 - $this->rating / 2; $i++) {
            $retour .= '&#9734;'; // empty star
        }
        return $retour;
    }

    /**
     * Summary of getPubDate
     * @return string
     */
    public function getPubDate()
    {
        if (empty($this->pubdate)) {
            return '';
        }
        $dateY = (int) substr($this->pubdate, 0, 4);
        if ($dateY > 102) {
            return str_pad(strval($dateY), 4, '0', STR_PAD_LEFT);
        }
        return '';
    }

    /**
     * Summary of getComment
     * @param bool $withSerie
     * @return string
     */
    public function getComment($withSerie = true)
    {
        $addition = '';
        $se = $this->getSerie();
        if (!empty($se) && $withSerie) {
            $addition = $addition . '<strong>' . localize('content.series') . '</strong>' . str_format(localize('content.series.data'), $this->seriesIndex, htmlspecialchars($se->name)) . "<br />\n";
        }
        //if (preg_match('/<\/(div|p|a|span)>/', $this->comment)) {
        return $addition . Format::html2xhtml($this->comment);
        //} else {
        //    return $addition . htmlspecialchars($this->comment);
        //}
    }

    /**
     * Summary of getDataFormat
     * @param string $format
     * @return Data|false
     */
    public function getDataFormat($format)
    {
        $reduced = array_filter($this->getDatas(), function ($data) use ($format) {
            return $data->format == $format;
        });
        return reset($reduced);
    }

    /**
     * @checkme always returns absolute path for single DB in PHP app here - cfr. internal dir for X-Accel-Redirect with Nginx
     * @param string $extension
     * @param int $idData
     * @param false $relative Deprecated
     * @return string|false|null string for file path, false for missing cover, null for missing data
     */
    public function getFilePath($extension, $idData = null, $relative = false)
    {
        if ($extension == "jpg" || $extension == "png") {
            if (empty($this->coverFileName)) {
                return $this->path . '/cover.' . $extension;
            } else {
                $ext = strtolower(pathinfo($this->coverFileName, PATHINFO_EXTENSION));
                if ($ext == $extension) {
                    return $this->coverFileName;
                }
            }
            return false;
        }
        $data = $this->getDataById($idData);
        if (!$data) {
            return null;
        }
        $file = $data->name . "." . strtolower($data->format);
        return $this->path . '/' . $file;
    }

    /**
     * Summary of getUpdatedEpub
     * @param int $idData
     * @param bool $sendHeaders
     * @return void
     */
    public function getUpdatedEpub($idData, $sendHeaders = true)
    {
        $data = $this->getDataById($idData);

        try {
            $epub = new EPub($data->getLocalPath(), ZipEdit::class);

            $epub->setTitle($this->title);
            $authorArray = [];
            foreach ($this->getAuthors() as $author) {
                $authorArray[$author->sort] = $author->name;
            }
            $epub->setAuthors($authorArray);
            $epub->setLanguage($this->getLanguages());
            $epub->setDescription($this->getComment(false));
            $epub->setSubjects($this->getTagsName());
            // -DC- Use cover file name
            // $epub->Cover2($this->getFilePath('jpg'), 'image/jpeg');
            $epub->setCoverFile($this->coverFileName, 'image/jpeg');
            $epub->setCalibre($this->uuid);
            $se = $this->getSerie();
            if (!empty($se)) {
                $epub->setSeries($se->name);
                $epub->setSeriesIndex(strval($this->seriesIndex));
            }
            $filename = $data->getUpdatedFilenameEpub();
            // @checkme this is set in fetch.php now
            if ($this->updateForKepub) {
                $epub->updateForKepub();
                $filename = $data->getUpdatedFilenameKepub();
            }
            $epub->download($filename, $sendHeaders);
        } catch (Exception $e) {
            echo 'Exception : ' . $e->getMessage();
        }
    }

    /**
     * The values of all the specified columns
     *
     * @param string[] $columns
     * @param bool $asArray
     * @return array<mixed>
     */
    public function getCustomColumnValues($columns, $asArray = false)
    {
        $result = [];
        $database = $this->databaseId;

        $columns = CustomColumnType::checkCustomColumnList($columns, $database);

        foreach ($columns as $lookup) {
            $col = CustomColumnType::createByLookup($lookup, $database);
            if (!is_null($col)) {
                $cust = $col->getCustomByBook($this);
                if (!is_null($cust)) {
                    if ($asArray) {
                        array_push($result, $cust->toArray());
                    } else {
                        array_push($result, $cust);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Summary of getLinkArray
     * @return array<LinkEntry|LinkFeed>
     */
    public function getLinkArray()
    {
        $database = $this->databaseId;
        $linkArray = [];

        $cover = new Cover($this);
        $coverLink = $cover->getCoverLink();
        if ($coverLink) {
            array_push($linkArray, $coverLink);
        }
        // @todo set height for thumbnail here depending on opds vs. html
        $height = (int) Config::get('html_thumbnail_height');
        $thumbnailLink = $cover->getThumbnailLink($height);
        if ($thumbnailLink) {
            array_push($linkArray, $thumbnailLink);
        }

        foreach ($this->getDatas() as $data) {
            if ($data->isKnownType()) {
                array_push($linkArray, $data->getDataLink(LinkEntry::OPDS_ACQUISITION_TYPE, $data->format));
            }
        }

        // don't use collection here, or OPDS reader will group all entries together - messes up recent books
        foreach ($this->getAuthors() as $author) {
            /** @var Author $author */
            array_push($linkArray, new LinkFeed($author->getUri(), 'related', str_format(localize('bookentry.author'), localize('splitByLetter.book.other'), $author->name), $database));
        }

        // don't use collection here, or OPDS reader will group all entries together - messes up recent books
        $serie = $this->getSerie();
        if (!empty($serie)) {
            array_push($linkArray, new LinkFeed($serie->getUri(), 'related', str_format(localize('content.series.data'), $this->seriesIndex, $serie->name), $database));
        }

        return $linkArray;
    }

    /**
     * Summary of getEntry
     * @param int $count
     * @return EntryBook
     */
    public function getEntry($count = 0)
    {
        return new EntryBook(
            $this->getTitle(),
            $this->getEntryId(),
            $this->getComment(),
            'text/html',
            $this->getLinkArray(),
            $this
        );
    }

    /* End of other class (author, series, tag, ...) initialization and accessors */

    // -DC- Get customisable book columns
    /**
     * Summary of getBookColumns
     * @return string
     */
    public static function getBookColumns()
    {
        $res = static::SQL_COLUMNS;
        if (!empty(Config::get('calibre_database_field_cover'))) {
            $res = str_replace('has_cover,', 'has_cover, ' . Config::get('calibre_database_field_cover') . ',', $res);
        }

        return $res;
    }

    /**
     * Summary of getBookById
     * @param int $bookId
     * @param ?int $database
     * @return ?Book
     */
    public static function getBookById($bookId, $database = null)
    {
        $query = 'select ' . static::getBookColumns() . '
from books ' . static::SQL_BOOKS_LEFT_JOIN . '
where books.id = ?';
        $result = Database::query($query, [$bookId], $database);
        while ($post = $result->fetchObject()) {
            $book = new Book($post, $database);
            return $book;
        }
        return null;
    }

    /**
     * Summary of getBookByDataId
     * @param int $dataId
     * @param ?int $database
     * @return ?Book
     */
    public static function getBookByDataId($dataId, $database = null)
    {
        $query = 'select ' . static::getBookColumns() . ', data.name, data.format
from data, books ' . static::SQL_BOOKS_LEFT_JOIN . '
where data.book = books.id and data.id = ?';
        $result = Database::query($query, [$dataId], $database);
        while ($post = $result->fetchObject()) {
            $book = new Book($post, $database);
            $data = new Data($post, $book);
            $data->id = $dataId;
            $book->datas = [$data];
            return $book;
        }
        return null;
    }

    /**
     * Summary of getDataByBook
     * @param Book $book
     * @return array<Data>
     */
    public static function getDataByBook($book)
    {
        $out = [];

        $sql = 'select id, format, name from data where book = ?';

        $ignored_formats = Config::get('ignored_formats');
        if (count($ignored_formats) > 0) {
            $sql .= " and format not in ('"
            . implode("','", $ignored_formats)
            . "')";
        }

        $database = $book->getDatabaseId();
        $result = Database::query($sql, [$book->id], $database);

        while ($post = $result->fetchObject()) {
            array_push($out, new Data($post, $book));
        }
        return $out;
    }
}
