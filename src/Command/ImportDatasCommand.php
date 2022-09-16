<?php

namespace App\Command;

use DateTime;
use PDO;
use PDOException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import-datas',
    description: 'Create a Database "test", two tables "donations" and "contributors" and import datas from the CSV File uploaded',
)]
class ImportDatasCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }

        ini_set('max_execution_time', 0);

        // Connect to mysql
        $dbhost = "localhost";
        $dbname = "test";
        $dbuser = "root";
        $dbpass = "";

        // Create the DataBase
        $pdo = new PDO("mysql:host=$dbhost", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        try {


            $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
            $pdo->exec($sql);
        } catch (PDOException $ex) {
            echo $ex->getMessage();
        }

        // Connect to the database and create tables
        $dbchar = "utf8";
        $dbuser = "testAdmin";
        $dbpass = "admin";
        $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=$dbchar", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);


        try {

            $pdo->exec("CREATE TABLE IF NOT EXISTS donations(
                                    Id int NOT NULL AUTO_INCREMENT,
                                    dateDon DATETIME NOT NULL,
                                    phone_number  VARCHAR(10) NOT NULL, 
                                    total_donations INT NOT NULL,
                                    PRIMARY KEY (Id),
                                    UNIQUE (phone_number))");


            $pdo->exec("CREATE TABLE IF NOT EXISTS contributors(
                                    Id int NOT NULL AUTO_INCREMENT,
                                    phone_number VARCHAR(10) NOT NULL REFERENCES donations (phone_number),
                                    postal_code VARCHAR(5) NOT NULL,
                                    department VARCHAR(2) NOT NULL,
                                    PRIMARY KEY (Id),    
                                    UNIQUE (phone_number,postal_code))");



        } catch (PDOException $ex) {
            echo $ex->getMessage();
        }

        try {
            $pdo->exec("ALTER TABLE contributors ADD CONSTRAINT FK_phone_numberContributors FOREIGN KEY (phone_number) REFERENCES donations(phone_number) ON DELETE CASCADE ");

        } catch (PDOException $ex){
            echo $ex->getMessage();
        }



        // Set your file here
        $file = 'src/Command/contact.csv';

        // Define two arrays for storing values
        $keys = array();
        $newArray = array();

        // PHP Function to convert CSV into array
        function convertCsvToArray($file, $delimiter)
        {

            if (($handle = fopen($_SERVER['DOCUMENT_ROOT'].$file, 'r')) !== FALSE) {
                $i = 0;
                while (($lineArray = fgetcsv($handle, 38, $delimiter, ';')) !== FALSE) {
                    for ($j = 0; $j < count($lineArray); $j++) {
                        $arr[$i][$j] = $lineArray[$j];
                    }
                    $i++;
                }
                fclose($handle);
            }
            return $arr;
        }

        // Call the function convert csv To Array
        $data = convertCsvToArray($file, ';');

        // Set number of elements (minus 1 because we shift off the first row)
        $count = count($data) - 1;

        // First row for label or name
        $labels = array_shift($data);
        foreach ($labels as $label) {
            $keys[] = $label;
        }

        // Assign keys value to ids, we add new parameter id here
        $keys[] = 'id';
        for ($i = 0; $i < $count; $i++) {
            $data[$i][] = $i;
        }

        // Combine both array
        for ($j = 0; $j < $count; $j++) {
            $d = array_combine($keys, $data[$j]);
            $newArray[$j] = $d;
        }

        // Convert array to json php using the json_encode()
        $arrayToJson = json_encode($newArray);

        // Convert json to associative array
        $jsonToArray = json_decode($arrayToJson, true);




        // Loop in array to import data in Database
        for ($row = 0; $row < count($jsonToArray) - 1; $row++) {

            // Create date format to Database
            $dateDon = DateTime::createFromFormat("d/m/Y H:i", $jsonToArray[$row]['Date']);
            $date = $dateDon->format("Y-m-d H:i");


            $montant = (int)$jsonToArray[$row]['Montant'];
            $phone = $jsonToArray[$row]['Tel'];
            $cp = $jsonToArray[$row]['Code postal'];
            $department = substr($cp, 0, 2);


            // Request to insert datas in Database, delete duplicate and update values
            try {
                $sqlDonations = $pdo->prepare("INSERT INTO donations(dateDon,total_donations,phone_number) 
                                    VALUES('$date','$montant','$phone')
                                    ON DUPLICATE KEY UPDATE phone_number = phone_number, total_donations = total_donations+$montant");

                $sqlDonations->execute();
                $sqlContributors = $pdo->prepare("INSERT INTO contributors(phone_number,postal_code,department) 
                                        VALUES('$phone','$cp','$department')
                                        ON DUPLICATE KEY UPDATE phone_number = phone_number, postal_code = postal_code");
                $sqlContributors->execute();
            } catch (PDOException $ex) {
                echo $ex->getMessage();
            }
        }



        $io->success('Database, tables "donations" and "contributors" were created, datas were inserted ! Well done !');

        return Command::SUCCESS;
    }
}
