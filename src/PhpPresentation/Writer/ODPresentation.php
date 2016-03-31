<?php
/**
 * This file is part of PHPPresentation - A pure PHP library for reading and writing
 * presentations documents.
 *
 * PHPPresentation is free software distributed under the terms of the GNU Lesser
 * General Public License version 3 as published by the Free Software Foundation.
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code. For the full list of
 * contributors, visit https://github.com/PHPOffice/PHPPresentation/contributors.
 *
 * @link        https://github.com/PHPOffice/PHPPresentation
 * @copyright   2009-2015 PHPPresentation contributors
 * @license     http://www.gnu.org/licenses/lgpl.txt LGPL version 3
 */

namespace PhpOffice\PhpPresentation\Writer;

use PhpOffice\Common\Adapter\Zip\ZipArchiveAdapter;
use PhpOffice\PhpPresentation\HashTable;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\AbstractDrawing;
use PhpOffice\PhpPresentation\Shape\Group;
use PhpOffice\PhpPresentation\Shape\Table;
use DirectoryIterator;

/**
 * ODPresentation writer
 */
class ODPresentation extends AbstractWriter implements WriterInterface
{
    /**
     * @var \PhpOffice\PhpPresentation\Shape\Chart[]
     */
    public $chartArray = array();

    /**
    * Use disk caching where possible?
    *
    * @var boolean
    */
    private $useDiskCaching = false;

    /**
     * Disk caching directory
     *
     * @var string
     */
    private $diskCachingDirectory;

    /**
     * Create a new \PhpOffice\PhpPresentation\Writer\ODPresentation
     *
     * @param PhpPresentation $pPhpPresentation
     */
    public function __construct(PhpPresentation $pPhpPresentation = null)
    {
        // Assign PhpPresentation
        $this->setPhpPresentation($pPhpPresentation);

        // Set up disk caching location
        $this->diskCachingDirectory = './';

        // Set HashTable variables
        $this->oDrawingHashTable = new HashTable();

        $this->setZipAdapter(new ZipArchiveAdapter());
    }

    /**
     * Save PhpPresentation to file
     *
     * @param  string    $pFilename
     * @throws \Exception
     */
    public function save($pFilename)
    {
        if (empty($pFilename)) {
            throw new \Exception("Filename is empty");
        }
        // If $pFilename is php://output or php://stdout, make it a temporary file...
        $originalFilename = $pFilename;
        if (strtolower($pFilename) == 'php://output' || strtolower($pFilename) == 'php://stdout') {
            $pFilename = @tempnam('./', 'phppttmp');
            if ($pFilename == '') {
                $pFilename = $originalFilename;
            }
        }

        // Initialize HashTable
        $this->getDrawingHashTable()->addFromSource($this->allDrawings());

        // Initialize Zip
        $oZip = $this->getZipAdapter();
        $oZip->open($pFilename);

        // Variables
        $oPresentation = $this->getPhpPresentation();
        $arrayChart = array();

        $oDir = new DirectoryIterator(dirname(__FILE__).DIRECTORY_SEPARATOR.'ODPresentation');
        foreach ($oDir as $oFile) {
            if (!$oFile->isFile()) {
                continue;
            }
            $class = __NAMESPACE__.'\\ODPresentation\\'.$oFile->getBasename('.php');
            $o = new \ReflectionClass($class);

            if ($o->isAbstract() || !$o->isSubclassOf('PhpOffice\PhpPresentation\Writer\ODPresentation\AbstractDecoratorWriter')) {
                continue;
            }
            echo "\n".dirname(__FILE__).DIRECTORY_SEPARATOR.'ODPresentation'."/".$oFile->getBasename('.php')."\n";
            $oService = $o->newInstance();
            $oService->setZip($oZip);
            $oService->setPresentation($oPresentation);
            $oService->setDrawingHashTable($this->getDrawingHashTable());
            $oService->setArrayChart($arrayChart);
            $oZip = $oService->render();
            $arrayChart = $oService->getArrayChart();
            unset($oService);
        }

        // Close file
        $oZip->close();

        // If a temporary file was used, copy it to the correct file stream
        if ($originalFilename != $pFilename) {
            if (copy($pFilename, $originalFilename) === false) {
                throw new \Exception("Could not copy temporary zip file $pFilename to $originalFilename.");
            }
            if (@unlink($pFilename) === false) {
                throw new \Exception('The file ' . $pFilename . ' could not be removed.');
            }
        }
    }

    /**
     * Get use disk caching where possible?
     *
     * @return boolean
     */
    public function hasDiskCaching()
    {
        return $this->useDiskCaching;
    }

    /**
     * Set use disk caching where possible?
     *
     * @param  boolean $pValue
     * @param  string $pDirectory Disk caching directory
     * @throws \Exception
     * @return \PhpOffice\PhpPresentation\Writer\ODPresentation
     */
    public function setUseDiskCaching($pValue = false, $pDirectory = null)
    {
        $this->useDiskCaching = $pValue;

        if (!is_null($pDirectory)) {
            if (is_dir($pDirectory)) {
                $this->diskCachingDirectory = $pDirectory;
            } else {
                throw new \Exception("Directory does not exist: $pDirectory");
            }
        }

        return $this;
    }

    /**
     * Get disk caching directory
     *
     * @return string
     */
    public function getDiskCachingDirectory()
    {
        return $this->diskCachingDirectory;
    }

    /**
     * Get an array of all drawings
     *
     * @return \PhpOffice\PhpPresentation\Shape\AbstractDrawing[] All drawings in PhpPresentation
     * @throws \Exception
     */
    protected function allDrawings()
    {
        // Get an array of all drawings
        $aDrawings  = array();

        // Loop trough PhpPresentation
        $slideCount = $this->getPhpPresentation()->getSlideCount();
        for ($i = 0; $i < $slideCount; ++$i) {
            // Loop trough images and add to array
            $iterator = $this->getPhpPresentation()->getSlide($i)->getShapeCollection()->getIterator();
            while ($iterator->valid()) {
                if ($iterator->current() instanceof AbstractDrawing && !($iterator->current() instanceof Table)) {
                    $aDrawings[] = $iterator->current();
                } elseif ($iterator->current() instanceof Group) {
                    $iterator2 = $iterator->current()->getShapeCollection()->getIterator();
                    while ($iterator2->valid()) {
                        if ($iterator2->current() instanceof AbstractDrawing && !($iterator2->current() instanceof Table)) {
                            $aDrawings[] = $iterator2->current();
                        }
                        $iterator2->next();
                    }
                }

                $iterator->next();
            }
        }

        return $aDrawings;
    }
}
