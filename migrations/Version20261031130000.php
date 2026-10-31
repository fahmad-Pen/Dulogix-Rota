<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Assign 112 approved Transporter IDs to verified existing drivers only';
    }

    public function up(Schema $schema): void
    {
        $assignments = [
            ['aamirdulo', 'Aamir Sohail', 'A1VHM869VR7YEE'],
            ['aarondulo', 'Aaron Kwasi Nyarko Osafo', 'A1GQC7MYJLC1K6'],
            ['aaronldulo', 'Aaron Langham Lunn', 'A26R985261UKBF'],
            ['abduldulo', 'Abdul Raafay', 'A1QBT1Q6LT5NR5'],
            ['abdulazizdulo', 'Abdulaziz Mohamed Elier', 'A3CAI6VJUAODK8'],
            ['akindirandulo', 'Akindiran Elejogun', 'A2K5R1YMUIBZ76'],
            ['alexandrudulo', 'Alexandru Chibrian', 'A3K4Z8KJGPSX1G'],
            ['amadeuszdulo', 'Amadeusz Szczepan Starzynski', 'A1G6EKPRQ4CUIN'],
            ['ameliadulo', 'Amelia Hannah Cayless', 'A25TB67ETBLBQV'],
            ['ammieldulo', 'Ammiel Aquila Saleem Thomas', 'A11C0C0HYY4Q9Z'],
            ['amritpaldulo', 'Amritpal Singh', 'A1Q3OPOTKDADYB'],
            ['aneesdulo', 'Anees Shaikh', 'A1UIM5I3RV4JQM'],
            ['arturdulo', 'Artur Petku', 'AYSWRLS4OIQJF'],
            ['atlehangdulo', 'Atlehang Kgau Tsalong', 'A1PF8BIRP0921Q'],
            ['baljitdulo', 'Baljit Singh', 'A1YQXFQMUQJ9U6'],
            ['basildulo', 'Basil Ahmad', 'A2I62SDSUEGFQS'],
            ['beatrisdulo', 'Beatris Florentina Zamfirescu', 'A2V89Q1PUMCLFM'],
            ['benjamindulo', 'Benjamin Mohammed Dagia', 'A2WZLUEPMG0XB1'],
            ['boguslawdulo', 'Boguslaw Stanislaw Szmydt', 'A2XP05F50FEB48'],
            ['caidulo', 'Cai Tembo Chetty', 'A265GUWND9N9JH'],
            ['callumjdulo', 'Callum James Bramley', 'A3Q2XWJA5BL3ST'],
            ['callumdulo', 'Callum Fletcher Ryan OSullivan', 'A31R1YVM6YC3OT'],
            ['camerondulo', 'Cameron Singh Sandhu', 'A3JAQK1T8HCA02'],
            ['christopherdulo', 'Christopher Ian Elsegood', 'AT4QB2H8UVPQO'],
            ['chukwuemekadulo', 'Chukwuemeka Joseph Edu', 'A3OXVJ18LVW0BC'],
            ['davidmdulo', 'David Munyaradzi Rukweza', 'AZT4KE1LQ1CKX'],
            ['daviddulo', 'David Salvador Marrucate', 'A1T6XR9KPI630T'],
            ['deborahdulo', 'Deborah Victoria Smallwood', 'A3R0AUHUEN5QZ6'],
            ['devdulo', 'Dev Bhatt', 'A190OVCNZDZFSB'],
            ['eviedulo', 'Evie Grace Ward', 'A21N5TD6E9XW82'],
            ['florindulo', 'Florin Necula', 'A2RTYS903THXL3'],
            ['gabrieldulo', 'Gabriel Haisan', 'A3URF3J4EBT95Q'],
            ['gemmadulo', 'Gemma Louise Holahan', 'A39HANSGQRNQ6L'],
            ['georgedulo', 'George Warwick Edward Spearing', 'AH7JZLURQ3SS9'],
            ['godwindulo', 'Godwin Omoiroro Okobidi', 'A3EN4GYYHY5HI5'],
            ['gopaiahdulo', 'GOPAIAH Arudra', 'A3M90QKPS5CVW8'],
            ['hakimdulo', 'Hakim Ibaghouchene', 'A1Q5I1MTCSMD7A'],
            ['hamzahdulo', 'Hamzah Vadia', 'AEKTC8P5NLSHC'],
            ['haroondulo', 'Haroon Noor Ahmed', 'A1JV4M0HVK3KNZ'],
            ['hasmukhdulo', 'Hasmukh Khodiyara', 'A34CJSOACGHWC'],
            ['hierrodulo', 'Hierro Fernandes', 'A334L9IO35WV3Y'],
            ['hosseindulo', 'Hossein Ali Zaytoun', 'A52RORAEAZAFD'],
            ['iuliandulo', 'Iulian Scorohod', 'A3K45C426UANJO'],
            ['jamesdulo', 'James Mburu', 'A3P9LA513AC6SU'],
            ['jaskiratdulo', 'Jaskirat Jaskirat Singh', 'A12XZO0YWT39PZ'],
            ['jaswinderdulo', 'Jaswinder Singh', 'A1B91GK6B3VZCS'],
            ['josephhdulo', 'Joseph Hugh Bowen', 'A3S427U7B28N4P'],
            ['josephdulo', 'Joseph Thomas Sprake', 'A1X4I1SPSXIL5B'],
            ['juandulo', 'Juan Anver Bhika', 'A21MHT3RBNWB04'],
            ['kevindulo', 'Kevin Atong Atonge', 'A3OJED2YMOJK9V'],
            ['kevingdulo', 'Kevin Graham Clifford', 'A2ST1AK18S5P2B'],
            ['kishordulo', 'Kishor Devabhai Goraniya', 'A11OZCG4I393ON'],
            ['koyesdulo', 'Koyes Miah', 'ASPVL105O6EHG'],
            ['labibdulo', 'Labib Umar Farooq', 'A1SAYL45J493B1'],
            ['lahcendulo', 'Lahcen BABAYA', 'ASM3GPA2BPKSY'],
            ['laurendulo', 'Lauren Jade Fisher', 'AYMCG9ORIHN1M'],
            ['laurentiudulo', 'Laurentiu Mihai Ciungara', 'A1PS44ZG7YW865'],
            ['lawrencedulo', 'Lawrence Theofilo Santos de Oliveira', 'A17TTNMREKQ4WO'],
            ['leighdulo', 'Leigh anne Marie Husband', 'A27YZ0UB0TRAM7'],
            ['lesterdulo', 'Lester Alred Rebello', 'A1HFEGYKZMPAA4'],
            ['louisdulo', 'Louis Paul Johnson', 'A2DN0DQEH9I380'],
            ['lukaszdulo', 'Lukasz szabat', 'A24B373LDNIW8M'],
            ['mahdidulo', 'Mahdi Wafa', 'A14XCFT47VLLJL'],
            ['manueldulo', 'Manuel George Calugaru', 'A2Z2LV8VBSGYPS'],
            ['marcindulo', 'Marcin Piotr Zebrowski', 'A2FSB5128KUGK4'],
            ['martindulo', 'Martin George Raynor', 'A30VEKXFH0VV9J'],
            ['mayankdulo', 'Mayank Rajendrakumar Sagar', 'AKRDJH3X6FB2'],
            ['mhamaddulo', 'Mhamad Zaitoun', 'A1IIBO3N8ZHZBS'],
            ['mihaidulo', 'Mihai Lingurariu', 'A20A2KBUOGFBH4'],
            ['miroslavdulo', 'Miroslav Hristov Kenderov', 'AHIE3YOA310R9'],
            ['moazzamdulo', 'Moazzam Sethi', 'A1YSR8CCHD3N8H'],
            ['mohameddulo', 'Mohamed Elsadig Ibrahim', 'A1DN9V0HZT2FFM'],
            ['mohamedsdulo', 'Mohamed Sameer Abdul Rasheed', 'AOT64LZ985TWG'],
            ['mohammaddulo', 'Mohammad Tahir Ahmadi', 'A2ISJFKWIFODO'],
            ['mohammadzdulo', 'Mohammad zain Khalifa', 'A1ZK57PQW00KUI'],
            ['muizdulo', 'Muiz Jahanzaib Malik', 'AJ9UHTOSBQ2Y1'],
            ['mustakimdulo', 'Mustakim Firoz Shaikh', 'A3NAQTH4YTI21D'],
            ['nickydulo', 'Nicky Stuart White', 'A1KS1BRFKJM22Z'],
            ['niteshdulo', 'Nitesh Kishor', 'A1PKD40FJ8HOA7'],
            ['omobolajidulo', 'Omobolaji Wasiu Olanrewaju', 'AQDYY1I1GBXKV'],
            ['ouseinidulo', 'Ouseini Mougne', 'A3BS9C3MPEGEPK'],
            ['pajtimdulo', 'Pajtim Cengu', 'A2RM7NVKHZQKOI'],
            ['pasqualedulo', 'Pasquale Durante', 'A17ZQBVEHI48YL'],
            ['peterdulo', 'Peter Taziona Liabunya', 'A2JSUMJGDBP1YO'],
            ['pratikdulo', 'Pratik Patel', 'ALTEZGGB6RFXK'],
            ['roshandulo', 'Roshan Hares', 'A2PUR8ES4MKBM7'],
            ['ruidulo', 'Rui Miguel Miranda da Silva', 'A2MP7CIACM0OI2'],
            ['saadoudulo', 'Saadou Diallo', 'A3PASNX4KFD09F'],
            ['sarbjitdulo', 'Sarbjit Singh Sandhu', 'A3BOXOD4LSN1UI'],
            ['scottdulo', 'Scott Mark Young', 'A216F2FVK71SS'],
            ['shamsherdulo', 'Shamsher Singh', 'A3BZBX2J5WDEBH'],
            ['simranpreetdulo', 'Simranpreet Singh', 'A3781B6UQGJ1VP'],
            ['suheldulo', 'Suhel Ahmad', 'A2HNUAL7Y2QIC2'],
            ['susandulo', 'Susan Lucy OConnell', 'ABNTG6EICPLW'],
            ['taranjeetdulo', 'Taranjeet Kaur', 'ACGXI9EX310HP'],
            ['tommydulo', 'Tommy Lee Bridgen', 'AIHE3SSJNY2S8'],
            ['tonydulo', 'Tony Ward', 'A3EKHVSYFX23TM'],
            ['umardulo', 'Umar Arif Patel', 'A2REQ8XNJSY3DU'],
            ['umaralidulo', 'Umarali Mahamedrafik Kukavati', 'ANHRKJ9IUGE53'],
            ['umerdulo', 'Umer Mahamedrafik Kukavati', 'A3MRBSAC50B8PG'],
            ['urujdulo', 'Uruj Rehman', 'A3EGBMOVKR75G0'],
            ['vinaydulo', 'vinay vinay', 'AHT5B96N7MMG9'],
            ['viniciusdulo', 'Vinicius Alcides Cardoso da Silva', 'A26QVXT88FMPJR'],
            ['virajdulo', 'Viraj Rakesh Chouhan', 'A1Y8BZJOXXO9T1'],
            ['vishaldulo', 'Vishal Babu', 'SMNB58JEP9W7'],
            ['vithushandulo', 'Vithushan Mahendrarajah', 'A2PRAQ2PLB1QDC'],
            ['waleddulo', 'Waled Abdallah', 'A1X9B9Z36SJNNS'],
            ['williamdulo', 'William John Hasler Cross', 'A1ZBT88KZMQD1P'],
            ['yauvandulo', 'Yauvan Babla', 'A1B7F6CXHFYAET'],
            ['aqeeldulo', 'Aqeel Hyder Razahussein Hudda', 'A1W0AYDJCFWO2B'],
            ['jaydeepdulo', 'Jaydeep Bhaidas', 'A1GV8MQH5IIPR8'],
            ['jayshreedulo', 'Jayshree Parbat Keshwala', 'A2RE1F85TFZOL'],
        ];
        $connection = $this->connection;
        $connection->beginTransaction();
        try {
            foreach ($assignments as [$username, $name, $transporterId]) {
                $row = $connection->fetchAssociative('SELECT id, display_name, roles, transporter_id FROM app_user WHERE username = ?', [$username]);
                if (!$row || $row['display_name'] !== $name || !in_array('ROLE_DRIVER', json_decode((string)$row['roles'], true) ?: [], true)) {
                    throw new \RuntimeException('Driver identity mismatch for username: '.$username);
                }
                if ($row['transporter_id'] === $transporterId) {
                    continue;
                }
                if ($row['transporter_id'] !== null && $row['transporter_id'] !== '') {
                    throw new \RuntimeException('Existing Transporter ID differs for username: '.$username);
                }
                $connection->executeStatement('UPDATE app_user SET transporter_id = ? WHERE id = ? AND transporter_id IS NULL', [$transporterId, $row['id']]);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('This approved identity mapping is intentionally not automatically reversible.');
    }
}
