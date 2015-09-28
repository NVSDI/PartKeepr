<?php


namespace PartKeepr\TipOfTheDayBundle\Services;


use Doctrine\ORM\EntityManager;
use PartKeepr\CronLoggerBundle\Services\CronLoggerService;
use PartKeepr\TipOfTheDayBundle\Entity\TipOfTheDay;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TipOfTheDayService
{

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var CronLoggerService
     */
    private $cronLoggerService;

    public function __construct(ContainerInterface $container, EntityManager $entityManager, CronLoggerService $cronLoggerService)
    {
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->cronLoggerService = $cronLoggerService;
    }

    /**
     * Syncronizes the tip database against the master wiki.
     *
     * @throws \Exception
     */
    public function syncTips()
    {
        if (ini_get("allow_url_fopen") == 0) {
            throw new \Exception("allow_url_fopen is disabled, but required to query the TipOfTheDay database.");
        }

        $uri = $this->container->getParameter("partkeepr.tip_of_the_day_list");

        $tipsString = file_get_contents($uri);

        $aPageNames = $this->extractPageNames($tipsString);

        $this->updateTipDatabase($aPageNames);

        $this->cronLoggerService->markCronRun("partkeepr:cron:synctips");
    }

    /**
     * Updates the tip database. Expects an array of page names.
     *
     * This method clears all page names and re-creates them. This saves
     * alot of engineering, because we don't need to match contents
     * within the database against contents in an array.
     *
     * @param array $aPageNames The page names as array. Page names are stored as string.
     */
    private function updateTipDatabase(array $aPageNames)
    {
        $dql = 'DELETE FROM PartKeepr\TipOfTheDayBundle\Entity\TipOfTheDay';
        $query = $this->entityManager->createQuery($dql);

        $query->execute();

        foreach ($aPageNames as $pageName) {
            $tip = new TipOfTheDay();
            $tip->setName($pageName);
            $this->entityManager->persist($tip);
        }

        $this->entityManager->flush();
    }

    /**
     * Extracts the page names from the mediawiki JSON returned.
     *
     * @param string $response The encoded json string
     *
     * @return array An array with the titles of each page
     */
    private function extractPageNames($response)
    {
        $aTipsStructure = json_decode($response, true);
        $aTips = $aTipsStructure["query"]["categorymembers"];

        $aPageNames = array();

        foreach ($aTips as $tip) {
            $aPageNames[] = $tip["title"];
        }

        return $aPageNames;
    }
}