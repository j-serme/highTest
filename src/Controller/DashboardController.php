<?php

namespace App\Controller;

use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(ChartBuilderInterface $chartBuilder): Response
    {
        // Connect to the Database with the instance of class PDO
        $dbhost = "localhost";
        $dbname = "test";
        $dbchar = "utf8";
        $dbuser = "testAdmin";
        $dbpass = "admin";
        $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=$dbchar", $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        /*
         * Retrieve the total mount grouped by mount
         */
        $sqlTotalDonations = $pdo->prepare("SELECT total_donations FROM donations GROUP BY total_donations");
        $sqlTotalDonations->execute();
        $dons = $sqlTotalDonations->fetchAll();
        $dons = (array)array_column($dons,'total_donations');

        /*
         * Use method prepare() and fetchAll() to store datas in $tels
         */
        $sqlCountPhones = $pdo->prepare("SELECT COUNT(phone_number) FROM donations GROUP BY total_donations");
        $sqlCountPhones->execute();
        $tels = $sqlCountPhones->fetchAll();

        /*
         * Loop on count of phone_number and push in new array $telsArray
         */
        $telsArray = [];
        foreach ($tels as $tel){
            $nombre = $tel[0];
           array_push($telsArray,$nombre);
        }

        /*
         * Request to get the 10 departments where there are the most contributors
         */
        $sqlDepartments = $pdo->prepare("SELECT COUNT(donations.phone_number),contributors.department, donations.total_donations 
                                                FROM contributors 
                                                JOIN donations ON donations.phone_number = contributors.phone_number
                                                GROUP BY contributors.department
                                                ORDER BY COUNT(donations.phone_number) DESC
                                                LIMIT 10; ");

        $sqlDepartments->execute();
        $departments = $sqlDepartments->fetchAll();

        /*
         * Stock the department in $cp and the number of contributors in $numberContributors and push in arrays $departmentArray and $numberContributorsArray
         */
        $departmentsArray = [];
        $numberContributorsArray = [];
        foreach ($departments as $department){
            $cp = $department['department'];
            $numberContributors = $department[0];
            array_push($departmentsArray,$cp);
            array_push($numberContributorsArray,$numberContributors);
        }

        /*
         * Request to get all the departments order by the count of contributors ranked in descending order
         */

        $test = $pdo->prepare("SELECT COUNT(donations.phone_number),contributors.department, donations.total_donations 
                                        FROM contributors 
                                        JOIN donations ON donations.phone_number = contributors.phone_number
                                        GROUP BY contributors.department
                                        ORDER BY COUNT(donations.phone_number) DESC");
        $test->execute();
        $testAll = $test->fetchAll();

        // method to compare the array with the 10 departments and the array of the all departments
        // and create an array. It is the eleventh part of pie chart.
        $otherDepartments = (array)array_diff_key($testAll,$departments);
        $numberContributorsAllDepartments = 0;
        foreach ($otherDepartments as $otherDepartementRow){
            $numberContributorsAllDepartments += $otherDepartementRow[0];
        }
        $numberContributorsArray[] = $numberContributorsAllDepartments;

        // Create and customize the BarChart

        $barChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $barChart->setData([
            'labels' => $dons,
            'datasets' => [
                [
                    'label' => 'Number of phone number per total donations',
                    'backgroundColor' => 'rgb(28, 103, 88)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => $telsArray,
                ],
            ],
        ]);

        // Create and configure the PieChart

        $pieChart = $chartBuilder->createChart(Chart::TYPE_PIE);
        $pieChart->setData([
            'labels' => $departmentsArray,
            'datasets' => [
                [
                    'label' => 'Répartition des donateurs uniques par département',
                    'backgroundColor' => 'rgb(93, 138, 168)',
                    'borderColor' => 'rgb(33, 171, 205)',
                    'data' => $numberContributorsArray,
                ],
            ],
        ]);




        /*
         * Generate the view by the method render()
         */
        return $this->render('dashboard/index.html.twig', [
            'view_title' => 'DASHBOARD',
            'barChart' => $barChart,
            'pieChart' => $pieChart
        ]);


    }

}
