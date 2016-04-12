<?php

namespace Caramia\Country\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Caramia\Country\Entity\Country;

class ImportCountriesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('caramia:import:countries')
            ->setDescription('Import json de la liste de pays')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->truncateTable();
        $output->writeln('Table truncated');

        $insertions_number = $this->importCountries();
        $output->writeln($insertions_number .' insertions');
    }

    protected function importCountries() {
        try{
            $json_countries = file_get_contents('./src/Ushop/Bundle/AppBundle/Resources/import/countries.json');

            if( !$json_countries ) {
                $output->writeln('La ressource n\'existe pas ou ne peut être ouverte.', PHP_EOL, 'Aucun fichier créé.');
            }
        } catch(Exception $e ) {
            $output->writeln($e);
        }


        $em = $this->getContainer()->get('doctrine')->getManager();
        $countries = json_decode($json_countries, true);
        $cpt = 0;
        foreach($countries as $country) {
            $entity = new Country();
            $entity->setNameEn($country['name']['common']);
            $entity->setNameFr($country['translations']['fra']['common']);
            $entity->setNameEs($country['translations']['spa']['common']);

            $keys   = array_keys($country['languages']);
            $lang = array_shift($keys);
            if(!$lang) {
                $lang = 'en';
            }
            switch ($lang) {
                case 'fra':
                    $locale = 'fr';
                    break;
                case 'spa':
                    $locale = 'es';
                    break;
                case 'eng':
                    $locale = 'en';
                    break;
                default:
                    $locale = $lang;
                break;
            }
            $entity->setLocale($locale);

            if( count($country['currency']) > 0 ) {
                $entity->setCurrency(array_values($country['currency'])[0]);
            }
            if( count($country['callingCode']) > 0 ) {
                $entity->setCallingCode($country['callingCode'][0]);
            }
            $entity->setIsoCode($country['cca3']);
            $entity->setIsoCode2($country['cca2']);
            $entity->setFlag($entity->getIsoCode() .'.svg');
            $em->persist($entity);
            ++$cpt;
        }
        $em->flush();
        return $cpt;
    }

    protected function truncateTable() {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $connection = $em->getConnection();
        $sql = 'SET FOREIGN_KEY_CHECKS=0;TRUNCATE TABLE admin__country;';
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $stmt->closeCursor();
    }
}