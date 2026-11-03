<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use InvalidArgumentException;

final class DriverPerformanceController extends AbstractController
{
    #[Route('/driver-performance', name: 'driver_performance_hub', methods: ['GET', 'POST'])]
    public function index(EntityManagerInterface $em, Request $request, Connection $db, CsrfTokenManagerInterface $csrfManager): Response
    {
        $level = SectionAccess::performanceLevel($this->getUser());
        if ($level === 'none') throw $this->createAccessDeniedException('Driver Performance access is not assigned.');
        $full = $level === 'full';
        $selectedWeek = (string) $request->query->get('week', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedWeek) || !($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $selectedWeek)) || $date->format('Y-m-d') !== $selectedWeek) {
            $date = new \DateTimeImmutable('now', new \DateTimeZone('Europe/London'));
        }
        $weekStart = $date->modify('monday this week')->format('Y-m-d');
        $tab = in_array($request->query->get('tab'), ['cc', 'pod', 'dsp', 'dwc', 'rules'], true) ? $request->query->get('tab') : 'weekly';
        $scoreCategories = ['fico' => ['FICO/EMENTOR (Safety · Ementor Score)', 20], 'dwc' => ['DWC (Deliveries · Delivery Workflow Compliance)', 10], 'psb' => ['PSB (Collections · Collection Fails /100 stops)', 5], 'dsc_dpmo' => ['DSC DPMO (Concessions)', 20], 'pod' => ['POD (Picture on Delivery)', 10], 'cc' => ['CC (Contact Compliance)', 15], 'ce' => ['CE (Escalations)', 15], 'cdf_dpmo' => ['DEX / CDF DPMO (Negative Feedback)', 5]];
        $ficoColors = ['red_below' => 800, 'amber_below' => 810, 'red_color' => '#ffc7ce', 'amber_color' => '#ffeb9c', 'green_color' => '#c6efce'];
        $dwcColors = ['red_below' => 90, 'green_above' => 95, 'red_color' => '#ffc7ce', 'amber_color' => '#ffeb9c', 'green_color' => '#c6efce'];
        $dpmoBands = [[3000,0],[2000,1],[1500,2.5],[1400,4],[1300,5],[1200,7],[1100,8],[1000,10],[900,13],[700,16],[600,18],[575,19],[550,20]];
        $dpmoColors = ["green_at_or_below" => 550, "red_above" => 1000, "green_color" => "#c6efce", "amber_color" => "#ffeb9c", "red_color" => "#ffc7ce"];
        $podBands = [[99.9,10],[99.5,9],[99,8.5],[98.5,5],[98,4],[97.5,3],[97,1],[96.5,0.75],[95,0.5],[94,0.25],[90,0]];
        $cdfBands = [[8000,0],[7000,1],[6000,1.5],[5000,2.5],[4500,4]];
        $cdfBelow = 4420.0;
        $cdfBelowPoints = 5.0;
        $cdfColors = ['green_below'=>1,'red_above'=>4,'green_color'=>'#c6efce','amber_color'=>'#ffeb9c','red_color'=>'#ffc7ce'];
        $ceBands = [[0,15]];
        $ceAbovePoints = 0.0;
        $ceColors = ['zero_color' => '#c6efce', 'positive_color' => '#ffc7ce', 'missing_color' => '#c6efce'];
        $ccBands = [[99.99,15],[99.8,12],[99.7,10],[99.6,9.5],[99.5,9],[99.3,8],[99.2,7],[98.8,5],[98.6,4.5],[98.5,4],[98.3,3],[98,2],[97,1],[96,0]];
        $ccColors = ['red_below' => 98, 'green_above' => 98.99, 'red_color' => '#ffc7ce', 'amber_color' => '#ffeb9c', 'green_color' => '#c6efce'];
        $podColors = ["red_below" => 97, "green_above" => 99.1, "green_color" => "#c6efce", "amber_color" => "#ffeb9c", "red_color" => "#ffc7ce"];
        $psbThreshold = 3.0;
        $psbPoints = 5.0;
        $psbBands = [[3.0,5.0]];
        $psbColors = ['green_color' => '#c6efce', 'red_color' => '#ffc7ce'];
        $dwcBands = [[99,10],[97.5,9],[95,8.5],[92.5,5],[90,4],[87.5,3],[85,1],[82.5,0.75],[80,0.5],[78.5,0.25],[75,0]];
        $ficoBands = [[845,20],[840,19],[835,18],[830,17],[820,16],[810,14],[800,8],[790,3],[780,2],[770,1],[760,0]];
        $focusDefaults = ['NONE'=>'Excellent Week, Well done','DCR'=>'DCR - Use customers safe place options, contact customers and ensure you reattempt all problem deliveries.','DPMO'=>'DPMO / Concessions - Please discuss your concessions with OSM. This will help you understand how to improve.','POD'=>'POD - Please follow picture on delivery process. Do not bypass pictures on attended deliveries.','CE'=>'Customer Escalation - Reach out to the OSM team for reasons behind your escalation and ways to improve.','DWC'=>'Delivery Workflow and In App Compliance - Read notes and use customers safe place preferences.','CC'=>'CC - When prompted to contact, ring for at least 15 seconds before hanging up, or text.','NOA'=>'NOA - Use the notify on arrival feature to improve contact compliance and customer feedback.','DEX'=>'Negative Customer Feedback - Follow delivery instructions, be mindful of customer property and follow correct delivery procedures.','FICO'=>'FICO - Driving safety score on Ementor needs to be 810+ to be classed as a safe driver.','NET'=>'Netradyne - Drive safely. Traffic lights, distractions, following distance and stop signs affect the team scorecard.','PBS'=>'Pickup Success - Collect the yellow returns label for customer returns, scan and complete collections at the pin.'];
        $focusMessages = $focusDefaults;
        $scorecardExplanation = '';
        $reportTitles = PerformanceReportTitle::DEFAULTS;
        $decoded = [];
        $scoreRules = [];
        foreach ($scoreCategories as $key => [$label, $points]) $scoreRules[$key] = ['label' => $label, 'maximum' => $points];
        if ($db->createSchemaManager()->tablesExist(['driver_performance_scoring_rules'])) {
            $saved = $db->fetchOne('SELECT rules_json FROM driver_performance_scoring_rules WHERE id = 1');
            if ($saved) {
                $decoded = json_decode((string)$saved, true);
                if (is_array($decoded)) {
                    if (isset($decoded['focus_messages']) && is_array($decoded['focus_messages'])) foreach ($focusDefaults as $key => $default) if (isset($decoded['focus_messages'][$key]) && is_string($decoded['focus_messages'][$key])) $focusMessages[$key] = $decoded['focus_messages'][$key];
                    if (isset($decoded['scorecard_explanation']) && is_string($decoded['scorecard_explanation'])) $scorecardExplanation = $decoded['scorecard_explanation'];
                    foreach ($reportTitles as $key => $default) if (isset($decoded['report_titles'][$key]) && is_string($decoded['report_titles'][$key]) && trim($decoded['report_titles'][$key]) !== '') $reportTitles[$key] = $decoded['report_titles'][$key];
                    foreach ($scoreRules as $key => $default) if (isset($decoded[$key]['maximum'])) $scoreRules[$key]['maximum'] = (float)$decoded[$key]['maximum'];
                    if (isset($decoded['psb_threshold']) && is_numeric($decoded['psb_threshold'])) $psbThreshold = (float)$decoded['psb_threshold'];
                    if (isset($decoded['psb_points']) && is_numeric($decoded['psb_points'])) $psbPoints = (float)$decoded['psb_points'];
                    if (isset($decoded['psb_bands']) && is_array($decoded['psb_bands']) && count($decoded['psb_bands']) >= 1 && count($decoded['psb_bands']) <= 40) $psbBands = $decoded['psb_bands'];
                    if (isset($decoded['psb_colors']) && is_array($decoded['psb_colors'])) $psbColors = array_merge($psbColors, $decoded['psb_colors']);
                    if (isset($decoded['dwc_colors']) && is_array($decoded['dwc_colors'])) $dwcColors = array_merge($dwcColors, $decoded['dwc_colors']);
                    if (isset($decoded['dwc_bands']) && is_array($decoded['dwc_bands']) && count($decoded['dwc_bands']) >= 1 && count($decoded['dwc_bands']) <= 40) $dwcBands = $decoded['dwc_bands'];
                    if (isset($decoded['fico_colors']) && is_array($decoded['fico_colors'])) $ficoColors = array_merge($ficoColors, $decoded['fico_colors']);
                    if (isset($decoded['fico_bands']) && is_array($decoded['fico_bands']) && count($decoded['fico_bands']) >= 1 && count($decoded['fico_bands']) <= 40) $ficoBands = $decoded['fico_bands'];
                    if (isset($decoded['dpmo_bands']) && is_array($decoded['dpmo_bands']) && count($decoded['dpmo_bands']) >= 1 && count($decoded['dpmo_bands']) <= 40) $dpmoBands = $decoded['dpmo_bands'];
                    if (isset($decoded['pod_bands']) && is_array($decoded['pod_bands']) && count($decoded['pod_bands']) >= 1 && count($decoded['pod_bands']) <= 40) $podBands = $decoded['pod_bands'];
                    if (isset($decoded['dpmo_colors']) && is_array($decoded['dpmo_colors'])) $dpmoColors = array_merge($dpmoColors, $decoded['dpmo_colors']);
                    if (isset($decoded['cdf_bands']) && is_array($decoded['cdf_bands']) && count($decoded['cdf_bands']) >= 1 && count($decoded['cdf_bands']) <= 40) $cdfBands = $decoded['cdf_bands'];
                    if (isset($decoded['cdf_below']) && is_numeric($decoded['cdf_below'])) $cdfBelow = (float)$decoded['cdf_below'];
                    if (isset($decoded['cdf_below_points']) && is_numeric($decoded['cdf_below_points'])) $cdfBelowPoints = (float)$decoded['cdf_below_points'];
                    if (isset($decoded['cdf_colors']) && is_array($decoded['cdf_colors'])) $cdfColors = array_merge($cdfColors, $decoded['cdf_colors']);
                    if (isset($decoded['ce_bands']) && is_array($decoded['ce_bands']) && count($decoded['ce_bands']) >= 1 && count($decoded['ce_bands']) <= 40) $ceBands = $decoded['ce_bands'];
                    if (isset($decoded['ce_above_points']) && is_numeric($decoded['ce_above_points'])) $ceAbovePoints = (float)$decoded['ce_above_points'];
                    if (isset($decoded['ce_colors']) && is_array($decoded['ce_colors'])) $ceColors = array_merge($ceColors, $decoded['ce_colors']);
                    if (isset($decoded['cc_bands']) && is_array($decoded['cc_bands']) && count($decoded['cc_bands']) >= 1 && count($decoded['cc_bands']) <= 40) $ccBands = $decoded['cc_bands'];
                    if (isset($decoded['cc_colors']) && is_array($decoded['cc_colors'])) $ccColors = array_merge($ccColors, $decoded['cc_colors']);
                    if (isset($decoded['pod_colors']) && is_array($decoded['pod_colors'])) $podColors = array_merge($podColors, $decoded['pod_colors']);
                }
            }
        }
        // Row colours are keyed by threshold, so sorting does not change their association.
        $colourBands = ['fico'=>$ficoBands,'dwc'=>$dwcBands,'psb'=>$psbBands,'dpmo'=>$dpmoBands,'pod'=>$podBands,'cc'=>$ccBands,'ce'=>$ceBands,'cdf'=>$cdfBands];
        $colourPalettes = ['fico'=>$ficoColors,'dwc'=>$dwcColors,'psb'=>$psbColors,'dpmo'=>$dpmoColors,'pod'=>$podColors,'cc'=>$ccColors,'ce'=>$ceColors,'cdf'=>$cdfColors];
        $conditionColours = is_array($decoded['condition_colours'] ?? null) ? $decoded['condition_colours'] : [];
        $savedRowColourOverrides = $conditionColours;
        foreach ($colourBands as $metric => $bands) {
            foreach ($bands as [$threshold, $points]) {
                $key = (string)(float)$threshold;
                if (isset($conditionColours[$metric][$key]) && is_string($conditionColours[$metric][$key]) && preg_match('/^#[0-9a-fA-F]{6}$/D', $conditionColours[$metric][$key])) continue;
                $p = $colourPalettes[$metric]; $v = (float)$threshold;
                $shade = match ($metric) {
                    'fico' => $v < $p['red_below'] ? 'red' : ($v < $p['amber_below'] ? 'amber' : 'green'),
                    'dwc','pod','cc' => $v < $p['red_below'] ? 'red' : ($v <= $p['green_above'] ? 'amber' : 'green'),
                    'dpmo' => $v <= $p['green_at_or_below'] ? 'green' : ($v <= $p['red_above'] ? 'amber' : 'red'),
                    'psb','ce' => $v == 0 ? 'green' : 'red',
                    'cdf' => (float)$points >= 5 ? 'green' : ((float)$points <= 0 ? 'red' : 'amber')
                };
                $conditionColours[$metric][$key] = $metric === 'ce' ? $p[($shade === 'green' ? 'zero' : 'positive').'_color'] : $p[$shade.'_color'];
            }
        }
        $saveRowColours = static function (string $metric, array $thresholds, array $submitted, array &$conditionColours): void {
            if (count($thresholds) !== count($submitted)) throw new \InvalidArgumentException('Choose a colour for each scoring condition.');
            $new = [];
            foreach ($thresholds as $i => $threshold) {
                $hex = $submitted[$i] ?? null;
                if (!is_string($hex) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Invalid condition colour.');
                $new[(string)(float)$threshold] = $hex;
            }
            $conditionColours[$metric] = $new;
        };
        if ($request->isMethod('POST') && $request->request->get('score_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('performance_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $values = $request->request->all('maximum');
            $total = 0;
            foreach ($scoreRules as $key => &$rule) {
                $raw = $values[$key] ?? null;
                if (!is_scalar($raw) || !is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100) throw new \InvalidArgumentException('Each category maximum must be between 0 and 100.');
                $rule['maximum'] = (float)$raw;
                $total += $rule['maximum'];
            }
            unset($rule);
            if (abs($total - 100) > 0.001) {
                $this->addFlash('score_error', 'The category weights currently add up to '.number_format($total, 1).' points. Please adjust them so the total is exactly 100.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&score_error=1');
            }
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=weekly&week='.$weekStart.'&rules_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('fico_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('fico_color_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $red = filter_var($request->request->get('red_below'), FILTER_VALIDATE_FLOAT);
            $amber = filter_var($request->request->get('amber_below'), FILTER_VALIDATE_FLOAT);
            if ($red === false || $amber === false || $red < 0 || $amber <= $red || $amber > 850) throw new \InvalidArgumentException('Enter valid FICO thresholds between 0 and 850, with amber above red.');
            $newColours = [];
            foreach (['red','amber','green'] as $shade) {
                $hex = (string)$request->request->get('fico_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Select valid FICO colours.');
                $newColours[$shade.'_color'] = $hex;
            }
            $ficoColors = array_merge($ficoColors, $newColours, ['red_below' => $red, 'amber_below' => $amber]);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&fico_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('fico_formula_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('fico_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('fico_threshold');
            $points = $request->request->all('fico_points');
            $bands = [];
            $ficoMaximum = (float)$scoreRules['fico']['maximum'];
            if ($ficoMaximum <= 0) { $this->addFlash('score_error', 'Set the FICO/EMENTOR maximum above zero before editing its formula.'); return $this->redirect('/driver-performance?tab=rules&week='.$weekStart); }
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) throw new \InvalidArgumentException('Enter between 1 and 40 FICO formula rows.');
            foreach (array_keys($thresholds) as $i) {
                $t = $thresholds[$i] ?? null;
                $v = $points[$i] ?? null;
                if (!is_scalar($t) || !preg_match('/^\d{1,3}$/', (string)$t) || (int)$t > 850 || (int)$t < 0
                    || !is_scalar($v) || !is_numeric($v) || (float)$v < 0 || (float)$v > $ficoMaximum)
                    throw new \InvalidArgumentException('FICO thresholds must descend strictly and points must be between 0 and the FICO category maximum.');
                $bands[] = [(int)$t, (float)$v];
            }
            usort($bands, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
            foreach ($bands as $i => $band) {
                if ($i > 0 && $band[0] === $bands[$i - 1][0]) {
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&fico_formula_error=duplicate');
                }
            }
            $ficoBands = $bands;
            $saveRowColours('fico', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&fico_formula_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('dwc_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('dwc_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $red = filter_var($request->request->get('dwc_red'), FILTER_VALIDATE_FLOAT);
            $green = filter_var($request->request->get('dwc_green'), FILTER_VALIDATE_FLOAT);
            if ($red === false || $green === false || $red < 0 || $green <= $red || $green > 100) {
                $this->addFlash('dwc_error', 'Enter valid colour thresholds: red below green, both between 0 and 100.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $newColours = [];
            foreach (['red', 'amber', 'green'] as $shade) {
                $hex = (string)$request->request->get('dwc_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Select valid DWC colours.');
                $newColours[$shade.'_color'] = $hex;
            }
            $dwcColors = array_merge($dwcColors, $newColours, ['red_below' => $red, 'green_above' => $green]);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&dwc_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('dwc_formula_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('dwc_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('dwc_threshold');
            $points = $request->request->all('dwc_points');
            $maximum = (float)$scoreRules['dwc']['maximum'];
            $bands = [];
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) {
                $this->addFlash('dwc_error', 'Enter between 1 and 40 DWC formula rows.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            foreach (array_keys($thresholds) as $i) {
                $t = $thresholds[$i]; $v = $points[$i] ?? null;
                if (!is_scalar($t) || !is_numeric($t) || (float)$t < 0 || (float)$t > 100 || !is_scalar($v) || !is_numeric($v) || (float)$v < 0 || (float)$v > $maximum) {
                    $this->addFlash('dwc_error', 'DWC thresholds must be 0–100% and points must be between zero and the saved DWC maximum.');
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
                }
                $bands[] = [(float)$t, (float)$v];
            }
            usort($bands, static fn($a,$b) => $b[0] <=> $a[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i-1][0]) {
                $this->addFlash('dwc_error', 'Duplicate DWC thresholds are not allowed.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $dwcBands = $bands;
            $saveRowColours('dwc', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&dwc_formula_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('psb_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('psb_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('psb_threshold');
            $points = $request->request->all('psb_points');
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) {
                $this->addFlash('psb_error', 'Enter between 1 and 40 PSB conditions.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $bands = [];
            foreach ($thresholds as $i => $threshold) {
                $awarded = $points[$i] ?? null;
                if (!is_scalar($threshold) || !is_numeric($threshold) || (float)$threshold < 0 || !is_scalar($awarded) || !is_numeric($awarded) || (float)$awarded < 0 || (float)$awarded > (float)$scoreRules['psb']['maximum']) {
                    $this->addFlash('psb_error', 'Enter non-negative thresholds and points within the PSB maximum.');
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
                }
                $bands[] = [(float)$threshold, (float)$awarded];
            }
            usort($bands, static fn($a, $b) => $a[0] <=> $b[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i - 1][0]) {
                $this->addFlash('psb_error', 'Duplicate PSB thresholds are not allowed.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $psbBands = $bands;
            [$psbThreshold, $psbPoints] = $psbBands[0];
            foreach (['green','red'] as $shade) {
                $hex = (string)$request->request->get('psb_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Select valid PSB colours.');
                $psbColors[$shade.'_color'] = $hex;
            }
            $saveRowColours('psb', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&psb_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && in_array($request->request->get('metric_action'), ['dpmo', 'pod', 'cc', 'cdf'], true)) {
            $metric = (string)$request->request->get('metric_action');
            if (!$full || !$this->isCsrfTokenValid($metric.'_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('metric_threshold');
            $points = $request->request->all('metric_points');
            $maximum = (float)$scoreRules[$metric === 'dpmo' ? 'dsc_dpmo' : ($metric === 'cdf' ? 'cdf_dpmo' : $metric)]['maximum'];
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) throw new \InvalidArgumentException('Enter 1–40 scoring rows.');
            $bands = [];
            foreach ($thresholds as $i => $t) {
                $v = $points[$i] ?? null;
                if (!is_scalar($t) || !is_numeric($t) || (float)$t < 0 || (in_array($metric, ['pod','cc'], true) && (float)$t > 100) || !is_scalar($v) || !is_numeric($v) || (float)$v < 0 || (float)$v > $maximum) throw new \InvalidArgumentException('Invalid scoring threshold or points.');
                $bands[] = [(float)$t, (float)$v];
            }
            usort($bands, static fn($a,$b) => $b[0] <=> $a[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i-1][0]) throw new \InvalidArgumentException('Duplicate thresholds are not allowed.');
            $low = filter_var($request->request->get('metric_low'), FILTER_VALIDATE_FLOAT);
            $high = filter_var($request->request->get('metric_high'), FILTER_VALIDATE_FLOAT);
            if ($low === false || $high === false || $low < 0 || $high <= $low || (in_array($metric, ['pod','cc'], true) && $high > 100)) throw new \InvalidArgumentException('Invalid colour thresholds.');
            $newColours = [];
            foreach (['green', 'amber', 'red'] as $shade) {
                $hex = (string)$request->request->get('metric_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Choose valid colour values.');
                $newColours[$shade.'_color'] = $hex;
            }
            if ($metric === 'cdf') {
                $cdfBands = $bands;
                $cdfColors = array_merge($newColours, ['green_below'=>$low,'red_above'=>$high]);
                $below = $request->request->get('cdf_below');
                $belowPoints = $request->request->get('cdf_below_points');
                if (!is_scalar($below) || !is_numeric($below) || (float)$below < 0 || !is_scalar($belowPoints) || !is_numeric($belowPoints) || (float)$belowPoints < 0 || (float)$belowPoints > $maximum) throw new \InvalidArgumentException('Invalid negative feedback fallback.');
                $cdfBelow = (float)$below;
                $cdfBelowPoints = (float)$belowPoints;
            }
            if ($metric === 'cc') { $ccBands = $bands; $ccColors = array_merge($newColours, ['red_below' => $low, 'green_above' => $high]); }
            elseif ($metric === 'pod') { $podBands = $bands; $podColors = array_merge($newColours, ['red_below' => $low, 'green_above' => $high]); }
            elseif ($metric === 'dpmo') { $dpmoBands = $bands; $dpmoColors = array_merge($newColours, ['green_at_or_below' => $low, 'red_above' => $high]); }
            $saveRowColours($metric, $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&metric_saved='.$metric);
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('ce_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('ce_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('ce_threshold');
            $points = $request->request->all('ce_points');
            $max = (float)$scoreRules['ce']['maximum'];
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) throw new \InvalidArgumentException('Enter 1–40 CE conditions.');
            $bands = [];
            foreach ($thresholds as $i => $threshold) {
                $awarded = $points[$i] ?? null;
                if (!is_scalar($threshold) || !is_numeric($threshold) || (float)$threshold < 0 || !is_scalar($awarded) || !is_numeric($awarded) || (float)$awarded < 0 || (float)$awarded > $max) throw new \InvalidArgumentException('Invalid CE threshold or points.');
                $bands[] = [(float)$threshold, (float)$awarded];
            }
            usort($bands, static fn($a,$b) => $a[0] <=> $b[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i-1][0]) throw new \InvalidArgumentException('Duplicate CE thresholds.');
            $above = $request->request->get('ce_above_points');
            if (!is_scalar($above) || !is_numeric($above) || (float)$above < 0 || (float)$above > $max) throw new \InvalidArgumentException('Invalid CE fallback points.');
            $colors = [];
            foreach (['zero','positive','missing'] as $shade) {
                $hex = (string)$request->request->get('ce_'.$shade.'_color');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Invalid CE colour.');
                $colors[$shade.'_color'] = $hex;
            }
            $ceBands = $bands;
            $ceAbovePoints = (float)$above;
            $ceColors = $colors;
            $saveRowColours('ce', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&ce_saved=1');
        }
        $notice = '';
        if ($request->isMethod('POST') && $tab === 'weekly' && $request->request->get('netradyne_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('netradyne_weekly', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $id = strtoupper(trim((string)$request->request->get('transporter_id', '')));
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
            if (!$driver || !in_array('ROLE_DRIVER', $driver->getRoles(), true)) throw new \InvalidArgumentException('Unknown driver Transporter ID.');
            $values = [];
            foreach (['d','fd','ss','speed','tl'] as $field) {
                $value = $request->request->get($field);
                if (!is_scalar($value) || !preg_match('/^-?\\d{1,5}$/', (string)$value) || (int)$value < -99999) throw new \InvalidArgumentException('Enter a whole number from -99999 to 99999 for each Netradyne field.');
                $values[] = (int)$value;
            }
            $db->executeStatement('INSERT INTO driver_netradyne_weekly (week_start, transporter_id, d, fd, ss, speed, tl, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE d=VALUES(d), fd=VALUES(fd), ss=VALUES(ss), speed=VALUES(speed), tl=VALUES(tl), updated_by=VALUES(updated_by), updated_at=NOW()', array_merge([$weekStart, $id], $values, [$this->getUser()->getId()]));
            if ($request->request->get('autosave') === '1') return new \Symfony\Component\HttpFoundation\JsonResponse(['saved' => true]);
            return $this->redirect('/driver-performance?tab=weekly&week='.$weekStart.'&netradyne_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('rules_action') === 'scorecard_text') {
            if (!$full || !$this->isCsrfTokenValid('scorecard_text', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $submitted = $request->request->all('focus_messages');
            foreach ($focusDefaults as $key => $default) {
                $value = trim((string)($submitted[$key] ?? ''));
                if (mb_strlen($value) > 1200) throw new \InvalidArgumentException('Focus text exceeds 1200 characters.');
                $focusMessages[$key] = $value;
            }
            $scorecardExplanation = trim((string)$request->request->get('scorecard_explanation', ''));
            if (mb_strlen($scorecardExplanation) > 12000) throw new \InvalidArgumentException('Scorecard explanation exceeds 12000 characters.');
            $savedSettings = is_array($decoded) ? $decoded : [];
            $savedSettings['focus_messages'] = $focusMessages;
            $savedSettings['scorecard_explanation'] = $scorecardExplanation;
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode($savedSettings, JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&scorecard_text_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('rules_action') === 'report_titles') {
            if (!$full || !$this->isCsrfTokenValid('report_titles', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $submitted = $request->request->all('report_titles');
            foreach (PerformanceReportTitle::DEFAULTS as $key => $default) {
                $value = trim((string)($submitted[$key] ?? ''));
                if ($value === '' || mb_strlen($value) > 150) {
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&report_titles_error=1');
                }
                $reportTitles[$key] = $value;
            }
            $savedSettings = is_array($decoded) ? $decoded : [];
            $savedSettings['report_titles'] = $reportTitles;
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode($savedSettings, JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&report_titles_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'dwc') {
            if (!$full || !$this->isCsrfTokenValid('dwc_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if (in_array($request->request->get('dwc_action'), ['ignore', 'delete'], true)) {
                if ($request->request->get('dwc_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_dwc_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dwc&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
                foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_dwc_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                    $db->executeStatement('UPDATE driver_dwc_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dwc&week='.$weekStart);
            }
            try {
                $sourceRows = (new PerformanceReportReader())->read($request->files->get('dwc_file'), (string)$request->request->get('dwc_paste', ''));
                PerformanceReportTitle::requireTitle($sourceRows, $reportTitles['dwc'], 'DWC/IADC');
                $entries = [];
                foreach ($sourceRows as $cells) {
                    $offset = count($cells) >= 4 && ctype_digit(trim((string)$cells[0])) ? 1 : 0;
                    if (count($cells) < $offset + 3) continue;
                    $id = strtoupper(preg_replace('/\s+/', '', (string)$cells[$offset]));
                    if (!preg_match('/^[A-Z0-9]{8,40}$/', $id) || !preg_match('/[A-Z]/', $id)) continue;
                    $values = [];
                    foreach ([$offset + 1, $offset + 2] as $i) {
                        $raw = trim((string)$cells[$i]);
                        if (in_array($raw, ['', '-'], true)) { $values[] = 0.0; continue; }
                        if (!preg_match('/^(100(?:\.0+)?|\d{1,2}(?:\.\d+)?)\s*%?$/', $raw, $match)) continue 2;
                        $values[] = (float)$match[1];
                    }
                    if (!isset($entries[$id])) $entries[$id] = $values;
                }
                if (!$entries) throw new \InvalidArgumentException('No valid DWC/IADC rows found. Expected Transporter ID, DWC %, IADC %.');
                $db->beginTransaction();
                try {
                    $db->executeStatement('DELETE FROM driver_dwc_iadc WHERE week_start = ?', [$weekStart]);
                    $matched = 0; $unmatched = []; $position = 0;
                    foreach ($entries as $id => [$dwc, $iadc]) {
                        ++$position;
                        $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                        if (!$driver || !in_array('ROLE_DRIVER', $driver->getRoles(), true)) { $unmatched[] = $id; $db->executeStatement('INSERT INTO driver_dwc_unmatched (week_start, transporter_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE transporter_id=VALUES(transporter_id)', [$weekStart, $id]); continue; }
                        $db->executeStatement('DELETE FROM driver_dwc_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                        $db->executeStatement('INSERT INTO driver_dwc_iadc (week_start, transporter_id, report_position, dwc_percent, iadc_percent) VALUES (?, ?, ?, ?, ?)', [$weekStart, $id, $position, $dwc, $iadc]);
                        ++$matched;
                    }
                    $db->commit();
                } catch (\Throwable $error) { $db->rollBack(); throw $error; }
                $notice = '<p style="color:#17683f">Saved '.$matched.' DWC/IADC results.</p>';
                if ($unmatched) $notice .= '<p style="color:#a42323">Unmatched Transporter IDs (not imported): '.htmlspecialchars(implode(', ', $unmatched), ENT_QUOTES, 'UTF-8').'. Create drivers and reimport if required.</p>';
            } catch (\InvalidArgumentException $error) {
                $notice = '<p style="color:#a42323">'.htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
        }
        if ($request->isMethod('POST') && $tab === 'dsp') {
            if (!$full || !$this->isCsrfTokenValid('dsp_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if (in_array($request->request->get('dsp_action'), ['ignore', 'delete'], true)) {
                if ($request->request->get('dsp_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_dsp_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dsp&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
                foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_dsp_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                    $db->executeStatement('UPDATE driver_dsp_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dsp&week='.$weekStart);
            }
            try {
                $entries = (new DspSummaryReader())->read($request->files->get('dsp_file'), (string)$request->request->get('dsp_paste', ''), $reportTitles['dsp']);
                $matched = 0; $unknown = [];
                $db->beginTransaction();
                try {
                    // Replace this week's report atomically so old rows do not linger.
                    $db->executeStatement('DELETE FROM driver_dsp_summary WHERE week_start = ?', [$weekStart]);
                    $position = 0;
                    foreach ($entries as $id => $values) {
                        ++$position;
                        $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                        if (!$user || !in_array('ROLE_DRIVER', $user->getRoles(), true)) {
                            $unknown[] = $id;
                            $db->executeStatement('INSERT INTO driver_dsp_unmatched (week_start, transporter_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE transporter_id=VALUES(transporter_id)', [$weekStart, $id]);
                            continue;
                        }
                        $metrics = [];
                        foreach ($values as $v) $metrics[] = in_array(trim((string)$v), ['', '-'], true) ? 0.0 : (float)str_replace([',', '%'], '', $v);
                        $db->executeStatement('INSERT INTO driver_dsp_summary (week_start, transporter_id, report_position, delivered, dcr, dsc_dpmo, lor_dpmo, pod, cc, ce, cdf_dpmo, psb) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge([$weekStart, $id, $position], $metrics));
                        $db->executeStatement('DELETE FROM driver_dsp_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                        ++$matched;
                    }
                    $db->commit();
                } catch (\Throwable $e) { $db->rollBack(); throw $e; }
                $notice = '<p style="color:#17683f">Imported '.$matched.' DSP driver results; '.count($unknown).' unmatched IDs.</p>';
            } catch (InvalidArgumentException $e) {
                $notice = '<p style="color:#a42323">'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
        }
        if ($request->isMethod('POST') && $tab === 'pod') {
            if (!$full || !$this->isCsrfTokenValid('pod_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if (in_array($request->request->get('pod_action'), ['ignore', 'delete'], true)) {
                if ($request->request->get('pod_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_pod_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=pod&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
                foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_pod_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                    $db->executeStatement('UPDATE driver_pod_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=pod&week='.$weekStart);
            }
            try {
                $file = $request->files->get('pod_file');
                if (!$file && trim((string)$request->request->get('pod_paste', '')) === '') throw new InvalidArgumentException('Choose a report file or paste report data.');
                $entries = (new PodReportReader())->read($file, (string)$request->request->get('pod_paste', ''), $reportTitles['pod']);
                $matched = 0;
                $unknown = [];
                $db->beginTransaction();
                try {
                    $position = 0;
                    foreach ($entries as $id => $values) {
                        ++$position;
                        $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                        if (!$user || !in_array('ROLE_DRIVER', $user->getRoles(), true)) {
                            $unknown[] = $id;
                            $db->executeStatement('INSERT INTO driver_pod_unmatched (week_start, transporter_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE transporter_id=VALUES(transporter_id)', [$weekStart, $id]);
                            continue;
                        }
                        ++$matched;
                        $db->executeStatement('INSERT INTO driver_pod_report (week_start, transporter_id, report_position, opportunities, success, bypass, rejects, blurry_photo, no_package, package_in_car, package_too_close, photo_too_dark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE report_position=VALUES(report_position), opportunities=VALUES(opportunities), success=VALUES(success), bypass=VALUES(bypass), rejects=VALUES(rejects), blurry_photo=VALUES(blurry_photo), no_package=VALUES(no_package), package_in_car=VALUES(package_in_car), package_too_close=VALUES(package_too_close), photo_too_dark=VALUES(photo_too_dark)', array_merge([$weekStart, $id, $position], $values));
                        $db->executeStatement('DELETE FROM driver_pod_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                    }
                    $db->commit();
                } catch (\Throwable $e) { $db->rollBack(); throw $e; }
                $notice = '<p style="color:#17683f">Saved '.$matched.' POD driver results. '.count($unknown).' unmatched IDs.</p>';
            } catch (InvalidArgumentException $e) {
                $notice = '<p style="color:#a42323">'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
        }
        if ($request->isMethod('POST') && in_array($request->request->get('cc_action'), ['ignore', 'delete'], true)) {
            if (!$full || !$this->isCsrfTokenValid('cc_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if ($request->request->get('cc_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_cc_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=cc&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
            foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_cc_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                $db->executeStatement('UPDATE driver_cc_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
            }
            return $this->redirect('/driver-performance?tab=cc&week='.$weekStart);
        }
        if ($request->isMethod('POST') && $tab === 'cc') {
            if (!$full) throw $this->createAccessDeniedException();
            if (!$this->isCsrfTokenValid('cc_import', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            try {
                $reader = new PerformanceReportReader();
                $parsedRows = $reader->read($request->files->get('cc_file'), (string)$request->request->get('cc_html', ''));
            } catch (InvalidArgumentException $error) {
                $parsedRows = [];
                $notice = '<p style="color:#a42323">'.htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
            if ($parsedRows && !PerformanceReportTitle::matches($parsedRows, $reportTitles['cc'])) {
                $notice = '<p style="color:#a42323">Incorrect report type. Expected Contact Compliance report containing &quot;'.htmlspecialchars($reportTitles['cc'], ENT_QUOTES, 'UTF-8').'&quot;. Existing data was not changed.</p>';
                $parsedRows = [];
            }
            // Match DSP: validate the configured title and actual numeric driver rows.
            $entries = [];
            foreach ($parsedRows as $cells) {
                $offset = count($cells) >= 5 && ctype_digit(trim((string)$cells[0])) ? 1 : 0;
                if (count($cells) < $offset + 4) continue;
                $id = strtoupper(preg_replace('/\s+/', '', $cells[$offset]));
                if (!preg_match('/^[A-Z0-9]{8,40}$/', $id) || !preg_match('/[A-Z]/', $id)) continue;
                $cells = array_slice($cells, $offset);
                foreach ([1, 2, 3] as $i) if (in_array(trim((string)$cells[$i]), ['', '-'], true)) $cells[$i] = $i === 3 ? '0%' : '0';
                if (!ctype_digit(str_replace(',', '', $cells[1])) || !ctype_digit(str_replace(',', '', $cells[2]))) continue;
                $addresses = (int) str_replace(',', '', $cells[1]);
                $contacts = (int) str_replace(',', '', $cells[2]);
                if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*%$/', $cells[3], $match)) continue;
                $rate = (float)$match[1];
                if ($rate > 100 || $contacts > $addresses || isset($entries[$id])) continue;
                $entries[$id] = [$addresses, $contacts, $rate];
            }
            if (!$entries) {
                if ($notice === '') $notice = '<p style="color:#a42323">No valid Contact Compliance rows found. Paste the HTML table, not a screenshot.</p>';
            } else {
                $matched = 0;
                $unknown = [];
                foreach ($entries as $id => $metrics) {
                    $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                    if (!$user || !in_array('ROLE_DRIVER', $user->getRoles(), true)) { $unknown[] = $id; continue; }
                    $matched++;
                }
                $ignored = array_values(array_intersect($unknown, array_filter(array_map('strtoupper', (array)$request->request->all('ignore_ids')))));
                $db->beginTransaction();
                try {
                    $reportPosition = 0;
                    foreach ($entries as $id => [$addresses, $contacts, $rate]) {
                        ++$reportPosition;
                        if (in_array($id, $unknown, true)) continue;
                        $db->executeStatement('INSERT INTO driver_contact_compliance (week_start, transporter_id, total_addresses, total_contacts, compliance_percent, report_position) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_addresses=VALUES(total_addresses), total_contacts=VALUES(total_contacts), compliance_percent=VALUES(compliance_percent), report_position=VALUES(report_position)', [$weekStart, $id, $addresses, $contacts, $rate, $reportPosition]);
                    }
                    foreach ($unknown as $id) {
                        $db->executeStatement('INSERT INTO driver_cc_unmatched (week_start, transporter_id, ignored) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ignored=VALUES(ignored)', [$weekStart, $id, in_array($id, $ignored, true) ? 1 : 0]);
                    }
                    $db->commit();
                } catch (\Throwable $error) { $db->rollBack(); throw $error; }
                $notice = '<p style="color:#17683f">Saved '.$matched.' matched driver results.</p>';
                if ($unknown) $notice .= '<p style="color:#a42323">'.count($unknown).' Transporter IDs are not linked to drivers. Create these driver accounts and import again, or ignore each ID below.</p>';

            }
        }
        if ($request->query->has('saved')) $notice = '<p style="color:#17683f">Contact Compliance report saved for '.(int)$request->query->get('saved').' drivers.</p>';
        $escape = static fn(?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        $count = 0;
        foreach ($em->getRepository(User::class)->findBy([], ['displayName' => 'ASC']) as $user) {
            if (!in_array('ROLE_DRIVER', $user->getRoles(), true)) continue;
            ++$count;
            $rows .= '<tr><td>'.$count.'</td><td>'.$escape($user->getDisplayName()).'</td>'
                .($full ? '<td>'.$escape($user->getTransporterId() ?: 'Not assigned').'</td>' : '')
                .'</tr>';
        }
        $ccRows = '';
        $ccCount = 0;
        $report = $db->fetchAllAssociative('SELECT transporter_id, total_addresses, total_contacts, compliance_percent FROM driver_contact_compliance WHERE week_start = ? ORDER BY report_position ASC, transporter_id ASC', [$weekStart]);
        $ccById = [];
        foreach ($report as $ccRecord) $ccById[strtoupper(trim((string)$ccRecord['transporter_id']))] = $ccRecord;
        foreach ($report as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $ccCount++;
            $ccRows .= '<tr><td>'.$ccCount.'</td><td>'.$escape($user->getDisplayName()).'</td>'.($full ? '<td>'.$escape($r['transporter_id']).'</td>' : '').'<td>'.(int)$r['total_addresses'].'</td><td>'.(int)$r['total_contacts'].'</td><td>'.number_format((float)$r['compliance_percent'], 2).'%</td></tr>';
        }
        $unmatchedRows = $db->fetchAllAssociative('SELECT transporter_id, ignored FROM driver_cc_unmatched WHERE week_start = ? ORDER BY transporter_id', [$weekStart]);
        $unmatchedPanel = '';
        if ($full && $unmatchedRows) {
            $ignoredCount = count(array_filter($unmatchedRows, static fn(array $row): bool => (bool) $row['ignored']));
            $allIgnored = $ignoredCount === count($unmatchedRows);
            $unmatchedPanel = $allIgnored ? '<details style="margin:10px 0"><summary style="cursor:pointer;color:#805200;font-weight:600">'.count($unmatchedRows).' IDs ignored · View ignored IDs</summary>' : '';
            $unmatchedPanel .= '<div style="margin:10px 0;padding:10px;background:#fff7ed;border:1px solid #f0c99c;border-radius:10px"><h3 style="margin:0 0 6px">Unmatched Transporter IDs</h3><p>Create missing drivers in <a href="/admin/users?view=drivers">Driver Management</a> and import the report again, or ignore individual IDs.</p><form method="post" action="/driver-performance?tab=cc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('cc_import')->getValue()).'"><input type="hidden" name="cc_action" value="ignore"><table><thead><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr></thead><tbody>';
            foreach ($unmatchedRows as $r) {
                $id = $escape($r['transporter_id']);
                $unmatchedPanel .= '<tr><td>'.$id.'</td><td>'.($r['ignored'] ? 'Ignored' : 'Needs driver account').'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$id.'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$id.'"></td></tr>';
            }
            $unmatchedPanel .= '</tbody></table><button type="submit">Save ignore choices</button> <button type="submit" name="cc_action" value="delete" onclick="return confirm(&quot;Delete selected unmatched IDs? Driver results will not be affected.&quot;)" style="background:#9f2338;color:white;border:0;border-radius:6px;padding:7px 12px">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        $ccPanel = '<section class="card" id="cc-panel"'.($tab !== 'cc' ? ' hidden' : '').'><h2>CC (Contact Compliance)</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$ccCount.' drivers imported'.($full && !empty($ignoredCount) ? ' · '.$ignoredCount.' IDs ignored' : '').'</p>'.$notice.$unmatchedPanel;
        if ($full) $ccPanel .= '<details class="dsp-import"><summary>Import / Replace CC Report <span style="font-size:12px;font-weight:500;opacity:.8">PDF · Excel · HTML · CSV</span></summary><form method="post" enctype="multipart/form-data" action="/driver-performance?tab=cc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('cc_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="cc-drop" for="cc-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="cc-file-name">No file selected</span></label><input id="cc-file" type="file" name="cc_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="cc-paste">Or paste report data</label><textarea id="cc-paste" name="cc_html" rows="6" maxlength="2000000" placeholder="Paste CC Report HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s CC Report records.</span><button class="dsp-import-button" type="submit">Import CC Report</button></div></form></details><style>.dsp-import{margin:12px 0 18px;border:1px solid #cbd9e8;border-radius:11px;overflow:hidden;background:#fff}.dsp-import>summary{cursor:pointer;background:#17456c;color:#fff;padding:14px 18px;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:12px;list-style:revert}.dsp-import>form{padding:20px}.dsp-import-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.dsp-dropzone{min-height:190px;border:2px dashed #9bb6d4;border-radius:12px;background:#f5f9fd;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:9px;padding:18px;text-align:center;cursor:pointer;color:#153c60}.dsp-dropzone:hover,.dsp-dropzone.is-dragging{border-color:#216ea8;background:#e8f3fd}.dsp-upload-icon{font-size:28px;font-weight:700;color:#216ea8}.dsp-file-label{font-size:13px;font-weight:600;color:#17683f;overflow-wrap:anywhere}.dsp-file-input{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden}.dsp-paste-label{display:block;font-weight:700;color:#173c5b;margin:0 0 9px}.dsp-import textarea{width:100%;min-height:146px;box-sizing:border-box;border:1px solid #b7c9dc;border-radius:9px;padding:12px;font:13px/1.5 monospace;resize:vertical;background:#fff}.dsp-import textarea:focus{outline:2px solid #74a9d4;outline-offset:1px}.dsp-muted{font-size:12px;color:#62778b;line-height:1.5}.dsp-import-actions{display:flex;justify-content:space-between;align-items:center;gap:16px;border-top:1px solid #e3eaf1;margin-top:18px;padding-top:16px}.dsp-import-button{background:#17456c;color:#fff;border:0;border-radius:8px;padding:12px 22px;font-weight:700;cursor:pointer}.dsp-import-button:hover{background:#22618f}@media(max-width:760px){.dsp-import-grid{grid-template-columns:1fr}.dsp-import-actions{align-items:stretch;flex-direction:column}.dsp-import-button{width:100%}}</style><script>(function(){["dsp","cc","pod","dwc"].forEach(function(prefix){const drop=document.getElementById(prefix+"-drop"),file=document.getElementById(prefix+"-file"),name=document.getElementById(prefix+"-file-name");if(!drop||!file||!name)return;file.addEventListener("change",function(){name.textContent=file.files.length?file.files[0].name:"No file selected"});["dragenter","dragover"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.add("is-dragging")})});["dragleave","drop"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.remove("is-dragging")})});drop.addEventListener("drop",function(e){if(e.dataTransfer&&e.dataTransfer.files.length){file.files=e.dataTransfer.files;file.dispatchEvent(new Event("change"))}})})})();</script>';
        $ccPanel .= $ccCount ? '<div style="overflow:auto"><table><thead><tr><th>#</th><th>Driver name</th>'.($full ? '<th>Transporter ID</th>' : '').'<th>Total addresses</th><th>Total contacts</th><th>Contact compliance</th></tr></thead><tbody>'.$ccRows.'</tbody></table></div>' : '<p>No Contact Compliance report imported for this week.</p>';
        $ccPanel .= '</section>';
        $podResults = $db->fetchAllAssociative('SELECT * FROM driver_pod_report WHERE week_start = ? ORDER BY report_position, transporter_id', [$weekStart]);
        $podById = [];
        foreach ($podResults as $podRecord) $podById[strtoupper(trim((string)$podRecord['transporter_id']))] = $podRecord;
        $podRows = '';
        $podCount = 0;
        foreach ($podResults as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $podRows .= '<tr><td>'.(++$podCount).'</td><td>'.$escape($user->getDisplayName()).'</td>'.($full ? '<td>'.$escape($r['transporter_id']).'</td>' : '');
            foreach (['opportunities','success','bypass','rejects','blurry_photo','no_package','package_in_car','package_too_close','photo_too_dark'] as $metric) $podRows .= '<td>'.(int)$r[$metric].'</td>';
            $podRows .= '</tr>';
        }
        $podUnmatched = $full ? $db->fetchAllAssociative('SELECT transporter_id, ignored FROM driver_pod_unmatched WHERE week_start = ? ORDER BY transporter_id', [$weekStart]) : [];
        $podIgnored = count(array_filter($podUnmatched, static fn($r) => (bool)$r['ignored']));
        $podPanel = '<section class="card"'.($tab !== 'pod' ? ' hidden' : '').'><h2>POD (Photo on Delivery)</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$podCount.' drivers imported'.($full && $podIgnored ? ' · '.$podIgnored.' IDs ignored' : '').'</p>'.($tab === 'pod' ? $notice : '');
        if ($full && $podUnmatched) {
            $allIgnored = $podIgnored === count($podUnmatched);
            $podPanel .= $allIgnored ? '<details><summary>'.count($podUnmatched).' IDs ignored · View ignored IDs</summary>' : '';
            $podPanel .= '<div style="padding:12px;border:1px solid #eebd87;background:#fff7ed;border-radius:9px;margin:10px 0"><strong>Unmatched Transporter IDs</strong><p>Create missing accounts in <a href="/admin/users?view=drivers">Driver Management</a> and reimport, or ignore IDs.</p><form method="post" action="/driver-performance?tab=pod&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('pod_import')->getValue()).'"><input type="hidden" name="pod_action" value="ignore"><table><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr>';
            foreach ($podUnmatched as $r) $podPanel .= '<tr><td>'.$escape($r['transporter_id']).'</td><td>'.($r['ignored'] ? 'Ignored' : 'Needs driver account').'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$escape($r['transporter_id']).'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$escape($r['transporter_id']).'"></td></tr>';
            $podPanel .= '</table><button type="submit">Save ignore choices</button> <button type="submit" name="pod_action" value="delete" onclick="return confirm(&quot;Delete selected unmatched IDs? Driver results will not be affected.&quot;)" style="background:#9f2338;color:white;border:0;border-radius:6px;padding:7px 12px">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        if ($full) $podPanel .= '<details class="dsp-import"><summary>Import / Replace POD Report <span style="font-size:12px;font-weight:500;opacity:.8">PDF · Excel · HTML · CSV</span></summary><form method="post" enctype="multipart/form-data" action="/driver-performance?tab=pod&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('pod_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="pod-drop" for="pod-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="pod-file-name">No file selected</span></label><input id="pod-file" type="file" name="pod_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="pod-paste">Or paste report data</label><textarea id="pod-paste" name="pod_paste" rows="6" maxlength="2000000" placeholder="Paste POD Report HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s POD Report records.</span><button class="dsp-import-button" type="submit">Import POD Report</button></div></form></details><style>.dsp-import{margin:12px 0 18px;border:1px solid #cbd9e8;border-radius:11px;overflow:hidden;background:#fff}.dsp-import>summary{cursor:pointer;background:#17456c;color:#fff;padding:14px 18px;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:12px;list-style:revert}.dsp-import>form{padding:20px}.dsp-import-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.dsp-dropzone{min-height:190px;border:2px dashed #9bb6d4;border-radius:12px;background:#f5f9fd;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:9px;padding:18px;text-align:center;cursor:pointer;color:#153c60}.dsp-dropzone:hover,.dsp-dropzone.is-dragging{border-color:#216ea8;background:#e8f3fd}.dsp-upload-icon{font-size:28px;font-weight:700;color:#216ea8}.dsp-file-label{font-size:13px;font-weight:600;color:#17683f;overflow-wrap:anywhere}.dsp-file-input{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden}.dsp-paste-label{display:block;font-weight:700;color:#173c5b;margin:0 0 9px}.dsp-import textarea{width:100%;min-height:146px;box-sizing:border-box;border:1px solid #b7c9dc;border-radius:9px;padding:12px;font:13px/1.5 monospace;resize:vertical;background:#fff}.dsp-import textarea:focus{outline:2px solid #74a9d4;outline-offset:1px}.dsp-muted{font-size:12px;color:#62778b;line-height:1.5}.dsp-import-actions{display:flex;justify-content:space-between;align-items:center;gap:16px;border-top:1px solid #e3eaf1;margin-top:18px;padding-top:16px}.dsp-import-button{background:#17456c;color:#fff;border:0;border-radius:8px;padding:12px 22px;font-weight:700;cursor:pointer}.dsp-import-button:hover{background:#22618f}@media(max-width:760px){.dsp-import-grid{grid-template-columns:1fr}.dsp-import-actions{align-items:stretch;flex-direction:column}.dsp-import-button{width:100%}}</style><script>(function(){["dsp","cc","pod","dwc"].forEach(function(prefix){const drop=document.getElementById(prefix+"-drop"),file=document.getElementById(prefix+"-file"),name=document.getElementById(prefix+"-file-name");if(!drop||!file||!name)return;file.addEventListener("change",function(){name.textContent=file.files.length?file.files[0].name:"No file selected"});["dragenter","dragover"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.add("is-dragging")})});["dragleave","drop"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.remove("is-dragging")})});drop.addEventListener("drop",function(e){if(e.dataTransfer&&e.dataTransfer.files.length){file.files=e.dataTransfer.files;file.dispatchEvent(new Event("change"))}})})})();</script>';
        $podPanel .= $podCount ? '<div style="overflow:auto"><table><thead><tr><th>#</th><th>Driver name</th>'.($full ? '<th>Transporter ID</th>' : '').'<th>Opportunities</th><th>Success</th><th>Bypass</th><th>Rejects</th><th>Blurry photo</th><th>No package</th><th>Package in car</th><th>Package too close</th><th>Photo too dark</th></tr></thead><tbody>'.$podRows.'</tbody></table></div>' : '<p>No POD report imported for this week.</p>';
        $podPanel .= '</section>';
        $dwcResults = $db->fetchAllAssociative('SELECT * FROM driver_dwc_iadc WHERE week_start = ? ORDER BY report_position, transporter_id', [$weekStart]);
        $dwcRows = ''; $dwcCount = 0;
        foreach ($dwcResults as $r) {
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$driver) continue;
            $dwcRows .= '<tr><td>'.(++$dwcCount).'</td><td><strong>'.$escape($driver->getDisplayName()).'</strong>'.($full ? '<div style="font-size:11px;color:#65788e">'.$escape($r['transporter_id']).'</div>' : '').'</td><td>'.number_format((float)$r['dwc_percent'], 2).'%</td><td>'.number_format((float)$r['iadc_percent'], 2).'%</td></tr>';
        }
        $dwcUnmatched = $full ? $db->fetchAllAssociative('SELECT transporter_id, ignored FROM driver_dwc_unmatched WHERE week_start = ? ORDER BY transporter_id', [$weekStart]) : [];
        $dwcIgnored = count(array_filter($dwcUnmatched, static fn($r) => (bool)$r['ignored']));
        $dwcPanel = '<section class="card"'.($tab !== 'dwc' ? ' hidden' : '').'><h2>DWC/IADC Report</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$dwcCount.' drivers imported'.($full && $dwcIgnored ? ' · '.$dwcIgnored.' IDs ignored' : '').'</p>'.($tab === 'dwc' ? $notice : '');
        if ($full && $dwcUnmatched) {
            $allIgnored = $dwcIgnored === count($dwcUnmatched);
            $dwcPanel .= $allIgnored ? '<details><summary>'.count($dwcUnmatched).' IDs ignored · View ignored IDs</summary>' : '';
            $dwcPanel .= '<div style="padding:12px;border:1px solid #eebd87;background:#fff7ed;border-radius:9px;margin:10px 0"><strong>Unmatched Transporter IDs</strong><p>Create missing drivers in <a href="/admin/users?view=drivers">Driver Management</a> and reimport, or ignore individual IDs.</p><form method="post" action="/driver-performance?tab=dwc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_import')->getValue()).'"><input type="hidden" name="dwc_action" value="ignore"><table><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr>';
            foreach ($dwcUnmatched as $r) $dwcPanel .= '<tr><td>'.$escape($r['transporter_id']).'</td><td>'.($r['ignored'] ? 'Ignored' : 'Needs driver account').'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$escape($r['transporter_id']).'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$escape($r['transporter_id']).'"></td></tr>';
            $dwcPanel .= '</table><button type="submit">Save ignore choices</button> <button type="submit" name="dwc_action" value="delete" onclick="return confirm(&quot;Delete selected unmatched IDs? Driver results will not be affected.&quot;)" style="background:#9f2338;color:white;border:0;border-radius:6px;padding:7px 12px">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        if ($full) $dwcPanel .= '<details class="dsp-import"><summary>Import / Replace DWC/IADC Report <span style="font-size:12px;font-weight:500;opacity:.8">PDF · Excel · HTML · CSV</span></summary><form method="post" enctype="multipart/form-data" action="/driver-performance?tab=dwc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="dwc-drop" for="dwc-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="dwc-file-name">No file selected</span></label><input id="dwc-file" type="file" name="dwc_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="dwc-paste">Or paste report data</label><textarea id="dwc-paste" name="dwc_paste" rows="6" maxlength="2000000" placeholder="Paste DWC/IADC Report HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s DWC/IADC Report records.</span><button class="dsp-import-button" type="submit">Import DWC/IADC Report</button></div></form></details><style>.dsp-import{margin:12px 0 18px;border:1px solid #cbd9e8;border-radius:11px;overflow:hidden;background:#fff}.dsp-import>summary{cursor:pointer;background:#17456c;color:#fff;padding:14px 18px;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:12px;list-style:revert}.dsp-import>form{padding:20px}.dsp-import-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.dsp-dropzone{min-height:190px;border:2px dashed #9bb6d4;border-radius:12px;background:#f5f9fd;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:9px;padding:18px;text-align:center;cursor:pointer;color:#153c60}.dsp-dropzone:hover,.dsp-dropzone.is-dragging{border-color:#216ea8;background:#e8f3fd}.dsp-upload-icon{font-size:28px;font-weight:700;color:#216ea8}.dsp-file-label{font-size:13px;font-weight:600;color:#17683f;overflow-wrap:anywhere}.dsp-file-input{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden}.dsp-paste-label{display:block;font-weight:700;color:#173c5b;margin:0 0 9px}.dsp-import textarea{width:100%;min-height:146px;box-sizing:border-box;border:1px solid #b7c9dc;border-radius:9px;padding:12px;font:13px/1.5 monospace;resize:vertical;background:#fff}.dsp-import textarea:focus{outline:2px solid #74a9d4;outline-offset:1px}.dsp-muted{font-size:12px;color:#62778b;line-height:1.5}.dsp-import-actions{display:flex;justify-content:space-between;align-items:center;gap:16px;border-top:1px solid #e3eaf1;margin-top:18px;padding-top:16px}.dsp-import-button{background:#17456c;color:#fff;border:0;border-radius:8px;padding:12px 22px;font-weight:700;cursor:pointer}.dsp-import-button:hover{background:#22618f}@media(max-width:760px){.dsp-import-grid{grid-template-columns:1fr}.dsp-import-actions{align-items:stretch;flex-direction:column}.dsp-import-button{width:100%}}</style><script>(function(){["dsp","cc","pod","dwc"].forEach(function(prefix){const drop=document.getElementById(prefix+"-drop"),file=document.getElementById(prefix+"-file"),name=document.getElementById(prefix+"-file-name");if(!drop||!file||!name)return;file.addEventListener("change",function(){name.textContent=file.files.length?file.files[0].name:"No file selected"});["dragenter","dragover"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.add("is-dragging")})});["dragleave","drop"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.remove("is-dragging")})});drop.addEventListener("drop",function(e){if(e.dataTransfer&&e.dataTransfer.files.length){file.files=e.dataTransfer.files;file.dispatchEvent(new Event("change"))}})})})();</script>';
        $dwcPanel .= $dwcCount ? '<div style="overflow:auto"><table><thead><tr><th>#</th><th>Driver name</th><th>DWC %</th><th>IADC %</th></tr></thead><tbody>'.$dwcRows.'</tbody></table></div>' : '<p>No DWC/IADC report imported for this week.</p>';
        $dwcPanel .= '</section>';
        $dspResults = $db->fetchAllAssociative('SELECT * FROM driver_dsp_summary WHERE week_start = ? ORDER BY report_position, transporter_id', [$weekStart]);
        $dspRows = ''; $weeklyRows = ''; $dspCount = 0;
        foreach ($dspResults as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $dspRows .= '<tr><td>'.(++$dspCount).'</td><td><strong>'.$escape($user->getDisplayName()).'</strong>'.($full ? '<div style="font-size:11px;color:#65788e;margin-top:3px">'.$escape($r['transporter_id']).'</div>' : '').'</td>';
            foreach (['delivered','dcr','dsc_dpmo','lor_dpmo','pod','cc','ce','cdf_dpmo','psb'] as $metric) {
                $value = $r[$metric] === null ? '-' : $r[$metric];
                if (in_array($metric, ['dcr','pod','cc'], true) && $value !== '-') $value = number_format((float)$value, 2).'%';
                $dspRows .= '<td>'.$escape((string)$value).'</td>';
            }
            $dspRows .= '</tr>';
        }
        $netradyneRows = $db->fetchAllAssociative('SELECT * FROM driver_netradyne_weekly WHERE week_start = ?', [$weekStart]);
        $netradyneById = array_column($netradyneRows, null, 'transporter_id');
        $dwcById = array_column($dwcResults, null, 'transporter_id');
        $weeklyFico = [];
        $weekEndExclusive = (new \DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
        foreach ($db->fetchAllAssociative('SELECT driver_id, shift_date, fisco_score FROM driver_daily_check WHERE shift_date >= ? AND shift_date < ? AND fisco_score IS NOT NULL ORDER BY shift_date DESC', [$weekStart, $weekEndExclusive]) as $check) {
            $driverId = (int)$check['driver_id'];
            if (!array_key_exists($driverId, $weeklyFico)) $weeklyFico[$driverId] = $check;
        }
        // Explicit weekly Ementor scores take priority over daily-check values.
        foreach ($db->fetchAllAssociative('SELECT e.transporter_id, e.ementor_score, u.id AS driver_id FROM driver_ementor_weekly e INNER JOIN app_user u ON u.transporter_id = e.transporter_id WHERE e.week_start = ?', [$weekStart]) as $score) {
            $weeklyFico[(int)$score['driver_id']] = ['fisco_score' => $score['ementor_score']];
        }
        $cdfPointsFor = static function (float $v) use ($cdfBands,$cdfBelow,$cdfBelowPoints): float {
            foreach ($cdfBands as [$min,$points]) if ($v >= (float)$min) return (float)$points;
            return $v < $cdfBelow ? $cdfBelowPoints : 0.0;
        };
        $cdfShadeFor = static function (float $points) use ($cdfColors): string {
            return $points >= 5 ? $cdfColors['green_color'] : ($points <= 0 ? $cdfColors['red_color'] : $cdfColors['amber_color']);
        };
        $rowShade = static function (string $metric, float $value) use ($colourBands, $savedRowColourOverrides): ?string {
            foreach ($colourBands[$metric] ?? [] as [$threshold, $points]) {
                $match = in_array($metric, ['psb','ce'], true) ? $value <= (float)$threshold : ($metric === 'cc' && (float)$threshold == 99.8 ? $value > (float)$threshold : $value >= (float)$threshold);
                if ($match) return $savedRowColourOverrides[$metric][(string)(float)$threshold] ?? null;
            }
            return null;
        };
        $scoreAtLeast = static function (float $value, array $bands, bool $strictCc = false): float {
            foreach ($bands as [$minimum, $points]) {
                if ($strictCc && (float)$minimum == 99.8 ? $value > (float)$minimum : $value >= (float)$minimum) return (float)$points;
            }
            return 0.0;
        };
        $scoreAtMost = static function (float $value, array $bands, float $fallback = 0.0): float {
            foreach ($bands as [$maximum, $points]) if ($value <= (float)$maximum) return (float)$points;
            return $fallback;
        };
        // Each of the five Netradyne categories costs 2 percentage points when nonzero.
        $netradynePenalty = static function (?array $values): int {
            $deduction = 0;
            foreach (['d', 'fd', 'ss', 'speed', 'tl'] as $key) {
                $count = max(0, (int)($values[$key] ?? 0));
                $deduction -= $count > 0 ? 2 : 0;
            }
            return $deduction;
        };
        $rankedRows = [];
        foreach ($dspResults as $record) {
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $record['transporter_id']]);
            if (!$driver) continue;
            $driverDwc = $dwcById[$record['transporter_id']] ?? null;
            $ficoRaw = $weeklyFico[$driver->getId()]['fisco_score'] ?? null;
            $scores = [
                'fico' => $ficoRaw === null ? null : $scoreAtLeast((float)$ficoRaw, $ficoBands),
                'dwc' => $driverDwc === null ? null : $scoreAtLeast((float)$driverDwc['dwc_percent'], $dwcBands),
                'psb' => $record['psb'] === null ? null : $scoreAtMost((float)$record['psb'], $psbBands),
                'dsc_dpmo' => $record['dsc_dpmo'] === null ? null : (static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= (float)$minimum) return (float)$points; return (float)end($dpmoBands)[1]; })((float)$record['dsc_dpmo']),
                'pod' => $record['pod'] === null ? null : $scoreAtLeast((float)$record['pod'], $podBands),
                'cc' => $record['cc'] === null ? null : $scoreAtLeast((float)$record['cc'], $ccBands, true),
                'ce' => $record['ce'] === null ? null : $scoreAtMost((float)$record['ce'], $ceBands, (float)$ceAbovePoints),
                'cdf_dpmo' => $record['cdf_dpmo'] === null ? null : $cdfPointsFor((float)$record['cdf_dpmo']),
            ];
            $complete = !in_array(null, $scores, true);
            $rankedRows[$record['transporter_id']] = ['complete' => $complete, 'total' => $complete ? max(0, round(array_sum($scores) + $netradynePenalty($netradyneById[$record['transporter_id']] ?? null), 2)) : null];
        }
        $ranking = [];
        foreach ($rankedRows as $id => $entry) if ($entry['complete']) $ranking[] = ['id'=>$id, 'total'=>$entry['total']];
        usort($ranking, static fn(array $a,array $b): int => ($b['total'] <=> $a['total']) ?: strcmp($a['id'], $b['id']));
        $topFiveRows = '';
        foreach (array_slice($ranking, 0, 5) as $index => $leader) {
            $leaderUser = $em->getRepository(User::class)->findOneBy(['transporterId' => $leader['id']]);
            if (!$leaderUser) continue;
            $rank = $index + 1;
            $topFiveRows .= '<div class="top-five-entry"><span class="top-five-trophy" aria-label="Rank '.$rank.'">🏆<small>'.$rank.'</small></span><span class="top-five-name">'.$escape($leaderUser->getDisplayName()).'</span><span class="top-five-score">'.$escape((string)$leader['total']).' / 100</span><span class="top-five-rank">Rank '.$rank.'</span></div>';
        }
        $topFiveWeek = (new \DateTimeImmutable($weekStart))->format('W');
        $topFivePanel = '<button type="button" id="top-five-open" class="top-five-button">🏆 Top 5 Drivers</button><dialog id="top-five-dialog" class="top-five-dialog"><div class="top-five-heading"><div><strong>🏆 Top 5 Drivers</strong><div class="top-five-week">Week '.$escape($topFiveWeek).' · '.$escape($weekStart).'</div></div><div class="top-five-heading-actions"><img src="/dulogix-logo.png" alt="Dulogix logo" class="top-five-logo"><button type="button" id="top-five-close" aria-label="Close top five drivers">✕</button></div></div><div class="top-five-list">'.($topFiveRows ?: '<p>No ranked drivers for this week yet.</p>') .'</div><div class="top-five-share"><button type="button" id="top-five-copy">📋 Copy</button><button type="button" id="top-five-whatsapp">💬 Share on WhatsApp</button></div><p id="top-five-feedback" class="top-five-feedback" aria-live="polite"></p></dialog>';
        $positions = [];
        $previousTotal = null;
        $previousRank = 0;
        foreach ($ranking as $i => $entry) {
            if ($previousTotal === null || $entry['total'] !== $previousTotal) $previousRank = $i + 1;
            $positions[$entry['id']] = $previousRank;
            $previousTotal = $entry['total'];
        }
        $priorWeekStart = (new \DateTimeImmutable($weekStart))->modify('-7 days')->format('Y-m-d');
        $priorDsp = $db->fetchAllAssociative('SELECT * FROM driver_dsp_summary WHERE week_start = ?', [$priorWeekStart]);
        $priorDwc = array_column($db->fetchAllAssociative('SELECT * FROM driver_dwc_iadc WHERE week_start = ?', [$priorWeekStart]), null, 'transporter_id');
        $priorNet = array_column($db->fetchAllAssociative('SELECT * FROM driver_netradyne_weekly WHERE week_start = ?', [$priorWeekStart]), null, 'transporter_id');
        $priorFico = [];
        foreach ($db->fetchAllAssociative('SELECT driver_id, fisco_score FROM driver_daily_check WHERE shift_date >= ? AND shift_date < ? AND fisco_score IS NOT NULL ORDER BY shift_date DESC', [$priorWeekStart, $weekStart]) as $check) {
            $id = (int)$check['driver_id'];
            if (!isset($priorFico[$id])) $priorFico[$id] = ['fisco_score'=>$check['fisco_score']];
        }
        foreach ($db->fetchAllAssociative('SELECT e.ementor_score, u.id AS driver_id FROM driver_ementor_weekly e INNER JOIN app_user u ON u.transporter_id = e.transporter_id WHERE e.week_start = ?', [$priorWeekStart]) as $check) {
            $priorFico[(int)$check['driver_id']] = ['fisco_score'=>$check['ementor_score']];
        }
        $priorRankedRows = [];
        foreach ($priorDsp as $record) {
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $record['transporter_id']]);
            if (!$driver) continue;
            $driverDwc = $priorDwc[$record['transporter_id']] ?? null;
            $ficoRaw = $priorFico[$driver->getId()]['fisco_score'] ?? null;
            $scores = [
                'fico' => $ficoRaw === null ? null : $scoreAtLeast((float)$ficoRaw, $ficoBands),
                'dwc' => $driverDwc === null ? null : $scoreAtLeast((float)$driverDwc['dwc_percent'], $dwcBands),
                'psb' => $record['psb'] === null ? null : $scoreAtMost((float)$record['psb'], $psbBands),
                'dsc_dpmo' => $record['dsc_dpmo'] === null ? null : (static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= (float)$minimum) return (float)$points; return (float)end($dpmoBands)[1]; })((float)$record['dsc_dpmo']),
                'pod' => $record['pod'] === null ? null : $scoreAtLeast((float)$record['pod'], $podBands),
                'cc' => $record['cc'] === null ? null : $scoreAtLeast((float)$record['cc'], $ccBands, true),
                'ce' => $record['ce'] === null ? null : $scoreAtMost((float)$record['ce'], $ceBands, (float)$ceAbovePoints),
                'cdf_dpmo' => $record['cdf_dpmo'] === null ? null : $cdfPointsFor((float)$record['cdf_dpmo']),
            ];
            $complete = !in_array(null, $scores, true);
            $priorRankedRows[$record['transporter_id']] = ['complete' => $complete, 'total' => $complete ? max(0, round(array_sum($scores) + $netradynePenalty($priorNet[$record['transporter_id']] ?? null), 2)) : null];
        }
        $priorRanking = [];
        foreach ($priorRankedRows as $id => $entry) if ($entry['complete']) $priorRanking[] = ['id'=>$id,'total'=>$entry['total']];
        usort($priorRanking, static fn(array $a,array $b): int => ($b['total'] <=> $a['total']) ?: strcmp($a['id'], $b['id']));
        $priorPositions = []; $priorTotal = null; $priorPosition = 0;
        foreach ($priorRanking as $i => $entry) {
            if ($priorTotal === null || $entry['total'] !== $priorTotal) $priorPosition = $i + 1;
            $priorPositions[$entry['id']] = $priorPosition;
            $priorTotal = $entry['total'];
        }
        usort($dspResults, static function(array $a,array $b) use ($rankedRows): int {
            $left = $rankedRows[$a['transporter_id']] ?? ['complete'=>false,'total'=>null];
            $right = $rankedRows[$b['transporter_id']] ?? ['complete'=>false,'total'=>null];
            return ($right['complete'] <=> $left['complete'])
                ?: (($right['total'] ?? -1) <=> ($left['total'] ?? -1))
                ?: strcmp($a['transporter_id'], $b['transporter_id']);
        });
        $statusForScore = static function (float $score): array {
            if ($score >= 93.5) return ['Fantastic_Plus', '#BFE8D0', '#14532D'];
            if ($score >= 85) return ['Fantastic', '#DDF2E7', '#246B4B'];
            if ($score >= 70) return ['Great', '#DCEBFA', '#245B91'];
            if ($score >= 40) return ['Fair', '#FFF0BF', '#8A5A12'];
            return ['Poor', '#FAD9DF', '#9F2338'];
        };
        $weeklyRows = '';
        foreach ($dspResults as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $cell = static fn($value, $suffix = ''): string => $value === null || $value === '' ? '—' : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8').$suffix;
            $net = $netradyneById[$r['transporter_id']] ?? null;
            $dwc = $dwcById[$r['transporter_id']] ?? null;
            $ccReport = $ccById[strtoupper(trim((string)$r['transporter_id']))] ?? null;
            $ccDetails = ($r['cc'] === null ? '' : '<div style="color:#bb2437;font-size:12px;font-weight:800;margin-bottom:4px">'.$escape((string)$scoreAtLeast((float)$r['cc'], $ccBands, true)).'%</div>').'<details class="metric-detail"><summary>';
            $ccDetails .= $r['cc'] === null ? '—' : '<span style="display:inline-block;padding:5px 8px;border-radius:5px;'.($rowShade('cc', (float)$r['cc']) !== null ? 'background:'.$escape((string)$rowShade('cc', (float)$r['cc'])).';color:#17314e' : ((float)$r['cc'] < $ccColors['red_below'] ? 'background:'.$escape((string)$ccColors['red_color']).';color:#9c0006' : ((float)$r['cc'] > $ccColors['green_above'] ? 'background:'.$escape((string)$ccColors['green_color']).';color:#006100' : 'background:'.$escape((string)$ccColors['amber_color']).';color:#9c5700'))).'">'.$cell($r['cc'], '%').'</span>';
            $ccDetails .= ' ▾</summary><div class="metric-detail-panel" style="position:absolute!important;right:0!important;left:auto!important;width:570px!important;min-width:0!important;max-width:calc(100vw - 32px)!important;box-sizing:border-box;padding:10px 12px!important;z-index:1000;display:block"><div style="display:flex;align-items:center;justify-content:space-between;gap:12px"><strong>CC report details</strong><span style="font-size:13px;font-weight:700;color:#31516f;text-align:right">'.$escape($user->getDisplayName()).'</span></div>';
            if ($ccReport) {
                $ccDetails .= '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px;margin-top:7px;width:100%;min-width:0">';
                foreach (['total_addresses'=>'Total addresses','total_contacts'=>'Total contacts','compliance_percent'=>'Contact compliance'] as $key => $label) {
                    $value = $key === 'compliance_percent' ? number_format((float)$ccReport[$key], 2).'%' : (string)(int)$ccReport[$key];
                    $ccDetails .= '<div style="padding:5px 4px;border:1px solid #d4e0ec;border-radius:5px;background:#f5f9fd;min-width:0;text-align:center"><div style="font-size:10px;line-height:1.2;color:#526a82">'.$escape($label).'</div><strong>'.$escape($value).'</strong></div>';
                }
                $ccDetails .= '</div>';
            } else {
                $ccDetails .= '<p>No imported CC details for this Transporter ID in the selected week.</p>';
            }
            $ccDetails .= '</div></details>';
            $podReport = $podById[strtoupper(trim((string)$r['transporter_id']))] ?? null;
            $podDetails = ($r['pod'] === null ? '' : '<div style="color:#bb2437;font-size:12px;font-weight:800;margin-bottom:4px">'.$escape((string)$scoreAtLeast((float)$r['pod'], $podBands)).'%</div>').'<details class="metric-detail"><summary>';
            $podDetails .= $r['pod'] === null ? '—' : '<span style="display:inline-block;padding:5px 8px;border-radius:5px;'.($rowShade('pod', (float)$r['pod']) !== null ? 'background:'.$escape((string)$rowShade('pod', (float)$r['pod'])).';color:#17314e' : ((float)$r['pod'] < $podColors['red_below'] ? 'background:'.$podColors['red_color'].';color:#9c0006' : ((float)$r['pod'] > $podColors['green_above'] ? 'background:'.$podColors['green_color'].';color:#006100' : 'background:'.$podColors['amber_color'].';color:#9c5700'))).'">'.$cell($r['pod'], '%').'</span>';
            $podDetails .= ' ▾</summary><div class="metric-detail-panel pod-detail-panel" style="position:absolute!important;right:0!important;left:auto!important;width:570px!important;min-width:0!important;max-width:calc(100vw - 32px)!important;box-sizing:border-box;padding:10px 12px!important;overflow-x:auto;z-index:1000;display:block"><div style="display:flex;align-items:center;justify-content:space-between;gap:12px"><strong>POD report details</strong><span style="font-size:13px;font-weight:700;color:#31516f;text-align:right">'.$escape($user->getDisplayName()).'</span></div>';
            if ($podReport) {
                $podDetails .= '<div style="display:grid;grid-template-columns:repeat(9,minmax(0,1fr));gap:4px;margin-top:7px;width:100%;min-width:0">';
                foreach (['opportunities'=>'Opportunities','success'=>'Success','bypass'=>'Bypass','rejects'=>'Rejects','blurry_photo'=>'Blurry photo','no_package'=>'No package','package_in_car'=>'Package in car','package_too_close'=>'Package too close','photo_too_dark'=>'Photo too dark'] as $key => $label) {
                    $podDetails .= '<div style="padding:5px 4px;border:1px solid #d4e0ec;border-radius:5px;background:#f5f9fd;min-width:0;text-align:center"><div style="font-size:10px;line-height:1.2;color:#526a82;overflow-wrap:anywhere">'.$escape($label).'</div><strong>'.(int)$podReport[$key].'</strong></div>';
                }
                $podDetails .= '</div>';
            } else {
                $podDetails .= '<p>No imported POD details for this Transporter ID in the selected week.</p>';
            }
            $podDetails .= '</div></details>';
            $netTotal = $netradynePenalty($net);
            $netColor = $netTotal < 0 ? '#b42335' : '#087a39';
            $netBackground = $netTotal < 0 ? '#ffe2e6' : '#c9f2d5';
            $netDetails = '<details class="metric-detail"><summary><span class="netradyne-total" style="display:inline-block;padding:4px 9px;border-radius:7px;font-weight:700;color:'.$netColor.';background:'.$netBackground.'">'.$netTotal.'%</span> ▾</summary><div class="metric-detail-panel"><strong>Netradyne violations</strong>';
            if ($full) {
                $netDetails .= '<form method="post" action="/driver-performance?tab=weekly&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('netradyne_weekly')->getValue()).'"><input type="hidden" name="netradyne_action" value="save"><input type="hidden" name="transporter_id" value="'.$escape($r['transporter_id']).'"><div class="netradyne-fields">';
                // Calculated penalties (read-only) above editable violation counts.
                foreach (['d'=>['D',2],'fd'=>['FD',2],'ss'=>['SS',2],'speed'=>['SPEED',2],'tl'=>['TL',2]] as $key=>$config) {
                    [$label, $multiplier] = $config;
                    $penalty = (int)($net[$key] ?? 0) > 0 ? -2 : 0;
                    $netDetails .= '<label>'.$label.'<input type="number" data-penalty="'.$key.'" value="'.$penalty.'" readonly tabindex="-1" style="background:#edf3f8;color:#243d57;font-weight:700"></label>';
                }
                $netDetails .= '</div><div style="font-size:12px;color:#58718c;margin:10px 0 6px">Violation counts (editable)</div><div class="netradyne-fields">';
                foreach (['d'=>'Distractions 25','fd'=>'Following Distance 5','ss'=>'Sign Signal 3','speed'=>'Speeding 15','tl'=>'Traffic Light 3'] as $key=>$label) $netDetails .= '<label>'.$label.'<input type="number" name="'.$key.'" min="-99999" max="99999" step="1" oninput="this.form.querySelectorAll(&quot;[data-penalty]&quot;).forEach(function(p){if(p.dataset.penalty===this.name)p.value=Number(this.value)>0?-2:0}.bind(this))" required value="'.$escape((string)($net[$key] ?? 0)).'"></label>';
                $netDetails .= '</div><small class="netradyne-save-status" aria-live="polite">Changes save automatically</small></form><script defer src="/netradyne-autosave.js?v=3"></script>';
            } else {
                foreach (['d'=>'D','fd'=>'FD','ss'=>'SS','speed'=>'SPEED','tl'=>'TL'] as $key=>$label) $netDetails .= '<span style="margin-right:12px">'.$label.': '.$escape((string)($net[$key] ?? '—')).'</span>';
            }
            $netDetails .= '</div></details>';
            $dwcScoreLabel = '';
            $dwcColour = '';
            if ($dwc) {
                $dwcValue = (float)$dwc['dwc_percent'];
                foreach ($dwcBands as [$minimum, $awarded]) if ($dwcValue >= (float)$minimum) { $dwcScoreLabel = '<div style="color:#bb2437;font-size:12px;font-weight:800;margin-bottom:4px">'.$escape((string)$awarded).'%</div>'; break; }
                $dwcColour = $dwcValue < (float)$dwcColors['red_below'] ? 'background:'.$escape((string)$dwcColors['red_color']).';color:#9c0006' : ($dwcValue <= (float)$dwcColors['green_above'] ? 'background:'.$escape((string)$dwcColors['amber_color']).';color:#9c5700' : 'background:'.$escape((string)$dwcColors['green_color']).';color:#006100');
            }
            if ($dwc) { $shade = $rowShade('dwc', $dwcValue); if ($shade !== null) $dwcColour = 'background:'.$escape($shade).';color:#17314e'; }
            $deliveryDetails = $dwcScoreLabel.'<details class="metric-detail"><summary><span style="padding:4px;border-radius:5px;'.$dwcColour.'">'.($dwc ? number_format((float)$dwc['dwc_percent'], 2).'%' : '—').'</span> ▾</summary><div class="metric-detail-panel"><strong>Delivery details</strong><div>Total delivered: '.$cell($r['delivered']).'</div><div>Delivery completion rate: '.$cell($r['dcr'], '%').'</div><div>In-app delivery compliance: '.($dwc ? number_format((float)$dwc['iadc_percent'], 2).'%' : '—').'</div></div></details>';
            $ficoWeightDisplay = '';
            if (isset($weeklyFico[$user->getId()])) {
                $rawFico = (float)$weeklyFico[$user->getId()]['fisco_score'];
                foreach ($ficoBands as [$minimum, $awarded]) {
                    if ($rawFico >= (float)$minimum) {
                        $weighted = (float)$awarded;
                        $ficoWeightDisplay = '<div style="color:#bb2437;font-size:12px;font-weight:800;margin-bottom:4px">'.htmlspecialchars(rtrim(rtrim(number_format($weighted, 2, '.', ''), '0'), '.'), ENT_QUOTES, 'UTF-8').'%</div>';
                        break;
                    }
                }
            }
            $qty = ($r['delivered'] !== null && $r['dsc_dpmo'] !== null) ? ((float)$r['delivered'] / 1000000) * (float)$r['dsc_dpmo'] : null;
            $weeklyRows .= '<tr><td><label class="driver-select-label"><input type="checkbox" class="driver-row-select" value="'.$escape((string)$r['transporter_id']).'" aria-label="Select '.$escape($user->getDisplayName()).'"><span><strong>'.$escape($user->getDisplayName()).'</strong>'.($full ? '<div style="font-size:11px;color:#65788e;margin-top:3px">'.$escape($r['transporter_id']).'</div>' : '').'<button type="button" class="driver-report-open" data-user-id="'.$escape((string)$user->getId()).'" data-driver="'.$escape($user->getDisplayName()).'" data-phone="'.$escape((string)$user->getPhone()).'" data-id="'.$escape($r['transporter_id']).'" data-rank="'.($rankedRows[$r['transporter_id']]['complete'] ? $escape((string)($positions[$r['transporter_id']] ?? '?')) : '—').'" data-prior-rank="'.$escape((string)($priorPositions[$r['transporter_id']] ?? '')).'" data-score="'.($rankedRows[$r['transporter_id']]['complete'] ? $escape((string)$rankedRows[$r['transporter_id']]['total']) : '—').'" data-status="'.($rankedRows[$r['transporter_id']]['complete'] ? $escape(str_replace('_', ' ', $statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[0])) : 'Pending').'" data-fico="'.$escape((string)($weeklyFico[$user->getId()]['fisco_score'] ?? '—')).'" data-fico-color="'.$escape((string)($rowShade('fico', (float)($weeklyFico[$user->getId()]['fisco_score'] ?? 0)) ?? ((float)($weeklyFico[$user->getId()]['fisco_score'] ?? 0) < (float)$ficoColors['red_below'] ? $ficoColors['red_color'] : ((float)($weeklyFico[$user->getId()]['fisco_score'] ?? 0) < (float)$ficoColors['amber_below'] ? $ficoColors['amber_color'] : $ficoColors['green_color'])))).'" data-fico-percent="'.$escape(($weeklyFico[$user->getId()]['fisco_score'] ?? null) !== null ? (string)$scoreAtLeast((float)$weeklyFico[$user->getId()]['fisco_score'], $ficoBands) : 'N/A').'" data-net-d="'.$escape((string)($net['d'] ?? 0)).'" data-net-fd="'.$escape((string)($net['fd'] ?? 0)).'" data-net-ss="'.$escape((string)($net['ss'] ?? 0)).'" data-net-speed="'.$escape((string)($net['speed'] ?? 0)).'" data-net-tl="'.$escape((string)($net['tl'] ?? 0)).'" data-dwc="'.$escape((string)($dwc['dwc_percent'] ?? '—')).'" data-dcr="'.$escape((string)($r['dcr'] ?? '—')).'" data-iadc="'.$escape((string)($dwc['iadc_percent'] ?? '—')).'" data-psb="'.$escape((string)($r['psb'] ?? '—')).'" data-dpmo="'.$escape((string)($r['dsc_dpmo'] ?? '—')).'" data-pod="'.$escape((string)($r['pod'] ?? '—')).'" data-cc="'.$escape((string)($r['cc'] ?? '—')).'" data-ce="'.$escape((string)($r['ce'] ?? '—')).'" data-cdf="'.$escape((string)($r['cdf_dpmo'] ?? '—')).'" data-pod-bypass="'.$escape((string)(($podReport['bypass'] ?? null) ?? 'N/A')).'" data-pod-rejects="'.$escape((string)(($podReport['rejects'] ?? null) ?? 'N/A')).'" data-pod-opportunities="'.$escape((string)(($podReport['opportunities'] ?? null) ?? 'N/A')).'" data-pod-success="'.$escape((string)(($podReport['success'] ?? null) ?? 'N/A')).'" data-cc-contacts="'.$escape((string)(($ccReport['total_contacts'] ?? null) ?? 'N/A')).'" data-cc-addresses="'.$escape((string)(($ccReport['total_addresses'] ?? null) ?? 'N/A')).'" data-qty="'.$escape((string)(($qty) ?? 'N/A')).'" data-dwc-award="'.$escape((string)(($dwc ? $scoreAtLeast((float)$dwc['dwc_percent'], $dwcBands) : null) ?? 'N/A')).'" data-psb-award="'.$escape((string)(($r['psb'] !== null ? $scoreAtMost((float)$r['psb'], $psbBands) : null) ?? 'N/A')).'" data-dpmo-award="'.$escape((string)($r['dsc_dpmo'] !== null ? (static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= (float)$minimum) return (float)$points; return (float)end($dpmoBands)[1]; })((float)$r['dsc_dpmo']) : 'N/A')).'" data-pod-award="'.$escape((string)(($r['pod'] !== null ? $scoreAtLeast((float)$r['pod'], $podBands) : null) ?? 'N/A')).'" data-cc-award="'.$escape((string)(($r['cc'] !== null ? $scoreAtLeast((float)$r['cc'], $ccBands, true) : null) ?? 'N/A')).'" data-ce-award="'.$escape((string)(($r['ce'] !== null ? $scoreAtMost((float)$r['ce'], $ceBands, (float)$ceAbovePoints) : null) ?? 'N/A')).'" data-cdf-award="'.$escape((string)(($r['cdf_dpmo'] !== null ? $cdfPointsFor((float)$r['cdf_dpmo']) : null) ?? 'N/A')).'" data-pod-success-percent="'.$escape((string)(($podReport && $r['delivered'] !== null && (float)$r['delivered'] > 0 ? round(100 * (float)$podReport['success'] / (float)$r['delivered'], 2) : null) ?? 'N/A')).'" data-pod-opportunities-percent="'.$escape((string)(($podReport && $r['delivered'] !== null && (float)$r['delivered'] > 0 ? round(100 * (float)$podReport['opportunities'] / (float)$r['delivered'], 2) : null) ?? 'N/A')).'" data-lor-dpmo="'.$escape((string)($r['lor_dpmo'] ?? 'N/A')).'" data-dcr-award="N/A"   data-net="'.$netTotal.'">📄 Report</button></span></label></td><td><strong>Rank: '.($rankedRows[$r['transporter_id']]['complete'] ? $escape((string)($positions[$r['transporter_id']] ?? '?')) : '—').'</strong><div>Status: '.($rankedRows[$r['transporter_id']]['complete'] ? '<span style="display:inline-block;padding:3px 7px;border-radius:5px;background:'.$statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[1].';color:'.$statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[2].';font-weight:600;font-size:12px">'.$escape(str_replace('_', ' ', $statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[0])).'</span>' : 'Pending').'</div><div style="white-space:nowrap">Score: '.($rankedRows[$r['transporter_id']]['complete'] ? $escape(rtrim(rtrim(number_format($rankedRows[$r['transporter_id']]['total'], 2, '.', ''), '0'), '.')) : '—').' / 100</div></td><td>'.(isset($weeklyFico[$user->getId()]) ? $ficoWeightDisplay.'<span style="display:inline-block;padding:6px 9px;border-radius:5px;background:'.($rowShade('fico', (float)$weeklyFico[$user->getId()]['fisco_score']) ?? ((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['red_below'] ? $ficoColors['red_color'] : ((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['amber_below'] ? $ficoColors['amber_color'] : $ficoColors['green_color']))).';color:'.((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['red_below'] ? '#9c0006' : ((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['amber_below'] ? '#9c5700' : '#006100')).'">'.$cell($weeklyFico[$user->getId()]['fisco_score']).'</span>' : '—').'</td><td>'.$netDetails.'</td><td>'.$deliveryDetails.'</td><td>'.($r['psb'] === null ? '—' : '<div style="font-size:12px;color:#b42332;font-weight:700">'.$escape((string)(static function(float $value) use ($psbBands): float { foreach ($psbBands as [$maximum, $points]) if ($value <= $maximum) return $points; return 0; })((float)$r['psb'])).'%'.'</div><span style="display:inline-block;padding:5px 8px;border-radius:5px;background:'.($rowShade('psb', (float)$r['psb']) ?? ((float)$r['psb'] == 0 ? $psbColors['green_color'] : $psbColors['red_color'])).';color:'.((float)$r['psb'] == 0 ? '#006100' : '#9c0006').'">'.$cell($r['psb']).'</span>').'</td><td>'.($r['dsc_dpmo'] === null ? '—' : '<div style="color:#bb2437;font-size:12px;font-weight:800">'.$escape((string)(static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= $minimum) return $points; return (float)end($dpmoBands)[1]; })((float)$r['dsc_dpmo'])).'%</div><span style="display:inline-block;padding:5px 8px;border-radius:5px;'.($rowShade('dpmo', (float)$r['dsc_dpmo']) !== null ? 'background:'.$escape((string)$rowShade('dpmo', (float)$r['dsc_dpmo'])).';color:#17314e' : ((float)$r['dsc_dpmo'] <= $dpmoColors['green_at_or_below'] ? 'background:'.$dpmoColors['green_color'].';color:#006100' : (((float)$r['dsc_dpmo'] > $dpmoColors['green_at_or_below'] && (float)$r['dsc_dpmo'] <= $dpmoColors['red_above']) ? 'background:'.$dpmoColors['amber_color'].';color:#9c5700' : ((float)$r['dsc_dpmo'] > $dpmoColors['red_above'] ? 'background:'.$dpmoColors['red_color'].';color:#9c0006' : '')))).'">'.$cell($r['dsc_dpmo']).'</span><div style="white-space:nowrap">Qty = '.($qty === null ? '—' : number_format($qty, 2)).'</div>').'</td><td>'.$podDetails.'</td><td>'.$ccDetails.'</td><td>'.($r['ce'] === null ? '<span style="display:inline-block;padding:5px 8px;border-radius:5px;background:'.$escape((string)$ceColors['missing_color']).';color:#006100">—</span>' : '<div style="color:#bb2437;font-size:12px;font-weight:800">'.$escape((string)(static function(float $v) use ($ceBands,$ceAbovePoints): float { foreach ($ceBands as [$maximum,$points]) if ($v <= $maximum) return $points; return $ceAbovePoints; })((float)$r['ce'])).'%</div><span style="display:inline-block;padding:5px 8px;border-radius:5px;background:'.$escape((string)($rowShade('ce', (float)$r['ce']) ?? ((float)$r['ce'] == 0 ? $ceColors['zero_color'] : $ceColors['positive_color']))).';color:'.((float)$r['ce'] == 0 ? '#006100' : '#9c0006').'">'.$cell($r['ce']).'</span>').'</td><td>'.($r['cdf_dpmo'] === null ? '—' : '<div style="color:#bd2337;font-size:12px;font-weight:800">'. $escape((string)$cdfPointsFor((float)$r['cdf_dpmo'])).'%</div><span style="display:inline-block;border-radius:5px;padding:5px 8px;background:'.$escape((string)($rowShade('cdf',(float)$r['cdf_dpmo']) ?? $cdfShadeFor($cdfPointsFor((float)$r['cdf_dpmo'])))).';color:#17314e">'.$cell($r['cdf_dpmo']).'</span>').'</td></tr>';
        }
        $dspUnmatched = $full ? $db->fetchAllAssociative('SELECT transporter_id, ignored FROM driver_dsp_unmatched WHERE week_start = ? ORDER BY transporter_id', [$weekStart]) : [];
        $dspIgnored = count(array_filter($dspUnmatched, static fn($r) => (bool)$r['ignored']));
        $dspPanel = '<section class="card"'.($tab !== 'dsp' ? ' hidden' : '').'><h2>DSP Daily Summary</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$dspCount.' drivers imported'.($full && $dspIgnored ? ' · '.$dspIgnored.' IDs ignored' : '').'</p>'.($tab === 'dsp' ? $notice : '');
        if ($full && $dspUnmatched) {
            $allIgnored = $dspIgnored === count($dspUnmatched);
            $dspPanel .= $allIgnored ? '<details><summary>'.$dspIgnored.' IDs ignored · View ignored IDs</summary>' : '';
            $dspPanel .= '<div style="padding:12px;border:1px solid #eebd87;background:#fff7ed;border-radius:9px;margin:10px 0"><strong>Unmatched Transporter IDs</strong><p>Create missing drivers in <a href="/admin/users?view=drivers">Driver Management</a> and reimport, or ignore IDs.</p><form method="post" action="/driver-performance?tab=dsp&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dsp_import')->getValue()).'"><input type="hidden" name="dsp_action" value="ignore"><table><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr>';
            foreach ($dspUnmatched as $r) $dspPanel .= '<tr><td>'.$escape($r['transporter_id']).'</td><td>'.($r['ignored'] ? 'Ignored' : 'Needs driver account').'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$escape($r['transporter_id']).'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$escape($r['transporter_id']).'"></td></tr>';
            $dspPanel .= '</table><button type="submit">Save ignore choices</button> <button type="submit" name="dsp_action" value="delete" onclick="return confirm(&quot;Delete selected unmatched IDs? Driver results will not be affected.&quot;)" style="background:#9f2338;color:white;border:0;border-radius:6px;padding:7px 12px">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        if ($full) $dspPanel .= '<details class="dsp-import"><summary>Import / Replace DSP Summary <span style="font-size:12px;font-weight:500;opacity:.8">PDF · Excel · HTML · CSV</span></summary><form id="dsp-import-form" method="post" enctype="multipart/form-data" action="/driver-performance?tab=dsp&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dsp_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="dsp-drop" for="dsp-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="dsp-file-name">No file selected</span></label><input id="dsp-file" type="file" name="dsp_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="dsp-paste">Or paste report data</label><textarea id="dsp-paste" name="dsp_paste" rows="6" maxlength="2000000" placeholder="Paste DSP Summary HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s DSP Summary records.</span><button class="dsp-import-button" type="submit">Import DSP Summary</button></div></form></details><style>.dsp-import{margin:12px 0 18px;border:1px solid #cbd9e8;border-radius:11px;overflow:hidden;background:#fff}.dsp-import>summary{cursor:pointer;background:#17456c;color:#fff;padding:14px 18px;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:12px;list-style:revert}.dsp-import>form{padding:20px}.dsp-import-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.dsp-dropzone{min-height:190px;border:2px dashed #9bb6d4;border-radius:12px;background:#f5f9fd;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:9px;padding:18px;text-align:center;cursor:pointer;color:#153c60}.dsp-dropzone:hover,.dsp-dropzone.is-dragging{border-color:#216ea8;background:#e8f3fd}.dsp-upload-icon{font-size:28px;font-weight:700;color:#216ea8}.dsp-file-label{font-size:13px;font-weight:600;color:#17683f;overflow-wrap:anywhere}.dsp-file-input{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden}.dsp-paste-label{display:block;font-weight:700;color:#173c5b;margin:0 0 9px}.dsp-import textarea{width:100%;min-height:146px;box-sizing:border-box;border:1px solid #b7c9dc;border-radius:9px;padding:12px;font:13px/1.5 monospace;resize:vertical;background:#fff}.dsp-import textarea:focus{outline:2px solid #74a9d4;outline-offset:1px}.dsp-muted{font-size:12px;color:#62778b;line-height:1.5}.dsp-import-actions{display:flex;justify-content:space-between;align-items:center;gap:16px;border-top:1px solid #e3eaf1;margin-top:18px;padding-top:16px}.dsp-import-button{background:#17456c;color:#fff;border:0;border-radius:8px;padding:12px 22px;font-weight:700;cursor:pointer}.dsp-import-button:hover{background:#22618f}@media(max-width:760px){.dsp-import-grid{grid-template-columns:1fr}.dsp-import-actions{align-items:stretch;flex-direction:column}.dsp-import-button{width:100%}}</style><script>(function(){["dsp","cc","pod","dwc"].forEach(function(prefix){const drop=document.getElementById(prefix+"-drop"),file=document.getElementById(prefix+"-file"),name=document.getElementById(prefix+"-file-name");if(!drop||!file||!name)return;file.addEventListener("change",function(){name.textContent=file.files.length?file.files[0].name:"No file selected"});["dragenter","dragover"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.add("is-dragging")})});["dragleave","drop"].forEach(function(type){drop.addEventListener(type,function(e){e.preventDefault();drop.classList.remove("is-dragging")})});drop.addEventListener("drop",function(e){if(e.dataTransfer&&e.dataTransfer.files.length){file.files=e.dataTransfer.files;file.dispatchEvent(new Event("change"))}})})})();</script>';
        $dspPanel .= $dspCount ? '<div style="overflow:auto"><table><thead><tr><th>#</th><th>Driver name</th><th>Delivered</th><th>DCR</th><th>DSC DPMO</th><th>LoR DPMO</th><th>POD</th><th>CC</th><th>CE</th><th>CDF DPMO</th><th>PSB</th></tr></thead><tbody>'.$dspRows.'</tbody></table></div>' : '<p>No DSP Summary report imported for this week.</p>';
        $dspPanel .= '</section>';
        $rulesPanel = '<div id="scoring-rules-editor" style="margin:16px 0;padding:16px;border:1px solid #d5e0ec;border-radius:10px"><h3>Scoring Rules · Category Weights</h3><p class="muted">Categories follow the Weekly Reports columns from left to right. Netradyne violations have no separate maximum-point row; QTY is calculated beneath DSC DPMO. FICO, DWC, DPMO and POD formulas are configurable; full driver totals remain pending.</p>'.($request->query->get('rules_saved') === '1' ? '<p style="color:green">Scoring weights saved.</p>' : '');
        foreach ($request->getSession()->getFlashBag()->get('score_error') as $scoreError) $rulesPanel .= '<p role="alert" style="color:#9b202b;background:#fff0f0;border:1px solid #f0b6bc;padding:12px;border-radius:8px">'.$escape($scoreError).'</p>';
        if ($full) $rulesPanel .= '<form method="post" action="/driver-performance?tab=weekly&amp;week='.$escape($weekStart).'"><input type="hidden" name="score_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('performance_rules')->getValue()).'">';
        $rulesPanel .= '<table><thead><tr><th>Category</th><th>Maximum points</th></tr></thead><tbody>';
        foreach ($scoreRules as $key => $rule) {
            $rulesPanel .= '<tr><td>'.$escape($rule['label']).($key === 'dsc_dpmo' && $full ? ' <button type="button" onclick="document.getElementById(&quot;dpmo-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit DPMO formula</button>' : '').($key === 'cdf_dpmo' && $full ? ' <button type="button" onclick="document.getElementById(&quot;cdf-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit Negative Feedback formula</button>' : '').($key === 'ce' && $full ? ' <button type="button" onclick="document.getElementById(&quot;ce-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit CE formula</button>' : '').($key === 'cc' && $full ? ' <button type="button" onclick="document.getElementById(&quot;cc-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit CC formula</button>' : '').($key === 'pod' && $full ? ' <button type="button" onclick="document.getElementById(&quot;pod-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit POD formula</button>' : '').($key === 'psb' && $full ? ' <button type="button" onclick="document.getElementById(&quot;psb-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit PSB formula</button>' : '').($key === 'dwc' && $full ? ' <button type="button" onclick="document.getElementById(&quot;dwc-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit DWC formula</button>' : '').($key === 'fico' && $full ? ' <button type="button" onclick="document.getElementById(&quot;fico-formula-dialog&quot;).showModal()" style="margin-left:12px;padding:7px 12px;background:#17456c;color:white;border:0;border-radius:6px;cursor:pointer">Edit FICO formula</button>' : '').'</td><td>'.($full ? '<input style="width:110px;margin:0" type="number" min="0" max="100" step="0.5" required name="maximum['.$escape($key).']" value="'.$escape((string)$rule['maximum']).'">' : $escape((string)$rule['maximum'])).'</td></tr>';
        }
        $rulesPanel .= '</tbody></table>';
        if ($full) $rulesPanel .= '<p id="weights-total" role="status" aria-live="polite" style="font-weight:700;margin:12px 0">Total: 100 / 100 ✓</p><p id="weights-warning" style="margin:6px 0;color:#a32929" hidden>The total must equal 100 before saving.</p><button id="weights-save" type="submit" style="padding:10px 16px;background:#17456c;color:white;border:0;border-radius:8px;cursor:pointer">Save scoring weights</button></form><script>document.addEventListener("DOMContentLoaded",function(){const form=document.querySelector("#scoring-rules-editor form"),total=document.getElementById("weights-total"),warning=document.getElementById("weights-warning"),save=document.getElementById("weights-save");if(!form||!total||!warning||!save)return;const inputs=[...form.querySelectorAll("input[name^=\"maximum[\"]")];function update(){const numbers=inputs.map(i=>i.value.trim()===""?NaN:Number(i.value));const sum=numbers.reduce((a,b)=>a+b,0);const valid=numbers.every(n=>Number.isFinite(n)&&n>=0&&n<=100)&&Math.abs(sum-100)<0.001;total.textContent="Total: "+(Number.isFinite(sum)?sum.toFixed(1).replace(/\\.0$/,""):"—")+" / 100 "+(valid?"✓":"— "+(Number.isFinite(sum)?(sum<100?(100-sum).toFixed(1)+" points short":(sum-100).toFixed(1)+" points over"):"Check values"));total.style.color=valid?"#17683f":"#a32929";warning.hidden=valid;save.disabled=!valid;save.style.opacity=valid?"1":"0.5";save.style.cursor=valid?"pointer":"not-allowed";}inputs.forEach(i=>i.addEventListener("input",update));update();});</script>';
        $rulesPanel .= '</div>';
        $rulesTabPanel = '<section class="card"'.($tab !== 'rules' ? ' hidden' : '').'><h2>Rules</h2>';
        $dwcDialog = '<dialog id="dwc-formula-dialog" style="border:1px solid #b8c9dd;border-radius:12px;max-width:550px;width:95%;padding:22px;box-shadow:0 16px 60px #0004"><h3>DWC scoring formula</h3><p class="muted">Enter minimum DWC percentages and manually awarded points. Values are never automatically scaled. Save the category maximum first.</p>';
        if ($full) $dwcDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="dwc_formula_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_formula_rules')->getValue()).'">';
        $dwcDialog .= '<div style="max-height:45vh;overflow:auto"><table><thead><tr><th>Minimum DWC %</th><th>Points / '.$escape((string)$scoreRules['dwc']['maximum']).'</th><th>Colour</th></tr></thead><tbody>';
        foreach ($dwcBands as $i => [$minimum, $awarded]) {
            $dwcDialog .= '<tr><td><input type="number" name="dwc_threshold['.$i.']" min="0" max="100" step="any" required value="'.$escape((string)$minimum).'" style="width:110px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="dwc_points['.$i.']" min="0" max="'.$escape((string)$scoreRules['dwc']['maximum']).'" step="any" required value="'.$escape((string)$awarded).'" style="width:110px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['dwc'][(string)(float)$minimum]).'" style="width:75px;height:42px;padding:4px;border:1px solid #b8c9dd;border-radius:7px"'.(!$full ? ' disabled' : '').'></td></tr>';
        }
        $dwcDialog .= '</tbody></table></div>';
        if ($full) $dwcDialog .= '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0"><button type="button" id="dwc-add">＋ Add row</button><button type="button" id="dwc-remove">− Remove last</button><button type="button" id="dwc-sort">↕ Sort scores</button></div><div style="display:flex;gap:10px"><button type="submit" style="background:#17456c;color:white;border:0;border-radius:7px;padding:10px">Save DWC formula</button><button type="button" onclick="document.getElementById(&quot;dwc-formula-dialog&quot;).close()">Close</button></div></form>';
        else $dwcDialog .= '<button type="button" onclick="document.getElementById(&quot;dwc-formula-dialog&quot;).close()">Close</button>';
        $dwcDialog .= '<div class="dwc-colour-editor" hidden style="margin-top:18px;padding:14px;background:#f7fafd;border:1px solid #d5e0ec;border-radius:9px"><h4>DWC · Conditional colours</h4><p class="muted">Set colour thresholds and choose the colours shown in Weekly Reports.</p><div class="dwc-colour-preview" style="display:flex;gap:8px;flex-wrap:wrap">';
        foreach (['red' => 'Red', 'amber' => 'Amber', 'green' => 'Green'] as $shade => $label) $dwcDialog .= '<span data-colour="'.$shade.'" style="padding:9px 12px;border-radius:7px;background:'.$escape((string)$dwcColors[$shade.'_color']).';color:#17314e">'.$label.'</span>';
        $dwcDialog .= '</div>';
        if ($full) {
            $dwcDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="dwc_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_rules')->getValue()).'"><div class="dwc-colour-grid"><label>Red below (%)<input type="number" name="dwc_red" min="0" max="100" step="any" required value="'.$escape((string)$dwcColors['red_below']).'"></label><label>Green above (%)<input type="number" name="dwc_green" min="0" max="100" step="any" required value="'.$escape((string)$dwcColors['green_above']).'"></label></div><div class="dwc-colour-grid">';
            foreach (['red' => 'Red', 'amber' => 'Amber', 'green' => 'Green'] as $shade => $label) $dwcDialog .= '<label>'.$label.' colour<input type="color" name="dwc_'.$shade.'_color" value="'.$escape((string)$dwcColors[$shade.'_color']).'"></label>';
            $dwcDialog .= '</div><button type="submit" style="background:#17456c;color:white;padding:10px 14px;border:0;border-radius:7px;cursor:pointer">Save DWC colours</button></form>';
        }
        $dwcDialog .= '</div>';
        $dwcDialog .= '</dialog><script>document.addEventListener("DOMContentLoaded",()=>{const dlg=document.getElementById("dwc-formula-dialog"),body=dlg.querySelector("tbody");const renumber=()=>Array.from(body.rows).forEach((r,i)=>r.querySelectorAll("input").forEach((x,j)=>x.name=(j===0?"dwc_threshold[":j===1?"dwc_points[":"condition_color[")+i+"]"));body.addEventListener("click",e=>{const r=e.target.closest("tr");if(!r)return;if(e.target.classList.contains("dwc-up")&&r.previousElementSibling)body.insertBefore(r,r.previousElementSibling);if(e.target.classList.contains("dwc-down")&&r.nextElementSibling)body.insertBefore(r.nextElementSibling,r);renumber()});const add=document.getElementById("dwc-add"),remove=document.getElementById("dwc-remove"),sort=document.getElementById("dwc-sort");if(add)add.onclick=()=>{if(body.rows.length>=40)return;const r=body.rows[body.rows.length-1].cloneNode(true);r.querySelectorAll("input").forEach((x,i)=>x.value=x.type==="color"?x.value:(i===1?0:Math.max(0,Number(x.value)-1)));body.appendChild(r);renumber();r.scrollIntoView({block:"nearest"})};if(remove)remove.onclick=()=>{if(body.rows.length>1)body.deleteRow(-1);renumber()};if(sort)sort.onclick=()=>{Array.from(body.rows).sort((a,b)=>Number(b.cells[0].querySelector("input").value)-Number(a.cells[0].querySelector("input").value)).forEach(r=>body.appendChild(r));renumber()};const form=dlg.querySelector("form");if(form)form.addEventListener("submit",renumber);dlg.querySelectorAll("input[type=color]").forEach(input=>input.addEventListener("input",()=>{const name=input.name.split("_")[1];const preview=dlg.querySelector(".metric-colour-preview span:nth-child("+({red:1,amber:2,green:3}[name])+")");if(preview)preview.style.background=input.value}))});</script>';
        $ficoColourPanel = '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0"><span style="background:'.$escape((string)$ficoColors['red_color']).';color:#9c0006;padding:12px;border-radius:7px">Red: below '.$escape((string)$ficoColors['red_below']).'</span><span style="background:'.$escape((string)$ficoColors['amber_color']).';color:#9c5700;padding:12px;border-radius:7px">Amber: '.$escape((string)$ficoColors['red_below']).' to below '.$escape((string)$ficoColors['amber_below']).'</span><span style="background:'.$escape((string)$ficoColors['green_color']).';color:#006100;padding:12px;border-radius:7px">Green: '.$escape((string)$ficoColors['amber_below']).' and above</span></div>';
        if ($full) $ficoColourPanel .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="fico_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('fico_color_rules')->getValue()).'"><label>Red if FICO below <input type="number" min="0" max="849" step="0.5" name="red_below" required value="'.$escape((string)$ficoColors['red_below']).'"></label><label>Amber if FICO below <input type="number" min="0.5" max="850" step="0.5" name="amber_below" required value="'.$escape((string)$ficoColors['amber_below']).'"></label><div class="fico-colour-pickers">'.implode('', array_map(static fn($shade) => '<label>'.ucfirst($shade).' colour<input type="color" name="fico_'.$shade.'_color" value="'.htmlspecialchars((string)$ficoColors[$shade.'_color'], ENT_QUOTES, 'UTF-8').'"></label>', ['red','amber','green'])).'</div><button type="submit" style="background:#17456c;color:white;padding:12px 18px;border:0;border-radius:7px">Save FICO colour rules</button></form>';
        $ficoColourPanel = '<div class="fico-colour-editor" style="margin-top:18px;padding:14px;border:1px solid #d5e0ec;border-radius:9px;background:#f7fafd"><h4 style="margin:0 0 8px">FICO · Conditional colours</h4><p class="muted">Colours apply to weekly FICO scores. Blank scores remain uncoloured.</p>'.($request->query->get('fico_saved') === '1' ? '<p style="color:#17683f">FICO colour rules saved.</p>' : '').$ficoColourPanel.'</div>';
        $formulaDialog = '<dialog id="fico-formula-dialog" style="border:1px solid #b8c9dd;border-radius:12px;max-width:520px;width:95%;padding:22px;box-shadow:0 16px 60px #0004"><h3>FICO / Ementor scoring formula</h3><p class="muted">For each minimum FICO score, award the corresponding points. Below the lowest threshold, no points are awarded. Formula points are manually entered and never rescaled when category weights change. Save category weights first; any formula points above the saved maximum must be edited manually.</p>';
        if ($full) $formulaDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="fico_formula_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('fico_formula_rules')->getValue()).'">';
        $formulaDialog .= '<div style="max-height:45vh;overflow:auto"><table><thead><tr><th>Minimum FICO</th><th>Points / '.$escape((string)$scoreRules['fico']['maximum']).'</th><th>Colour</th></tr></thead><tbody>';
        foreach ($ficoBands as $i => [$minimum, $awarded]) {
            $formulaDialog .= '<tr><td>'.($full ? '<input type="number" min="0" max="850" step="1" required name="fico_threshold['.$i.']" value="'.$escape((string)$minimum).'" style="width:110px;margin:0">' : $escape((string)$minimum)).'</td><td>'.($full ? '<input type="number" min="0" max="'.$escape((string)$scoreRules['fico']['maximum']).'" step="any" required name="fico_points['.$i.']" value="'.$escape((string)(float)$awarded).'" style="width:110px;margin:0">' : $escape((string)$awarded)).'</td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['fico'][(string)(float)$minimum]).'" style="width:75px;height:42px;padding:4px;border:1px solid #b8c9dd;border-radius:7px"'.(!$full ? ' disabled' : '').'></td></tr>';
        }
        $formulaDialog .= '</tbody></table></div>';
        if ($full) $formulaDialog .= '<div class="fico-formula-actions"><button type="button" class="fico-action fico-action-add" id="fico-add-row">＋ Add row</button><button type="button" class="fico-action fico-action-remove" id="fico-remove-row">− Remove last</button><button type="button" class="fico-action fico-action-sort" id="fico-sort-rows">↕ Sort scores</button></div><p class="muted" style="font-size:12px">Use Sort scores to order thresholds. Save requires descending FICO scores.</p>';
        if ($full) $formulaDialog .= '<div style="display:flex;align-items:center;gap:12px;margin-top:12px"><button type="submit" style="padding:10px 14px;background:#17456c;color:white;border:0;border-radius:7px">Save FICO formula</button><button type="button" onclick="document.getElementById(&quot;fico-formula-dialog&quot;).close()" style="padding:9px 14px;border:1px solid #b8c9dd;border-radius:7px;background:#f1f5fa;cursor:pointer">Close</button></div></form>';
        else $formulaDialog .= '<form method="dialog"><button style="margin-top:12px;padding:9px 14px">Close</button></form>';
        
        $formulaDialog .= '</dialog>';
        $formulaDialog .= '<script>document.addEventListener("DOMContentLoaded",function(){const dlg=document.getElementById("fico-formula-dialog");if(!dlg)return;const body=dlg.querySelector("tbody");const add=document.getElementById("fico-add-row");const remove=document.getElementById("fico-remove-row");if(add)add.addEventListener("click",function(){const n=body.rows.length;if(n>=40)return;const row=body.insertRow();const last=body.rows[n-1];for(let i=0;i<2;i++){const cell=row.insertCell();const input=document.createElement("input");input.type="number";input.name=(i===0?"fico_threshold[":"fico_points[")+n+"]";input.min="0";input.max=i===0?"850":String(document.querySelector("#fico-formula-dialog input[name^=fico_points]").max);input.step=i===0?"1":"any";input.required=true;input.style.width="110px";input.value=i===0?Math.max(0,Number(last.cells[0].querySelector("input").value)-5):0;cell.appendChild(input)}const colourCell=row.insertCell();const colourInput=last.querySelector("input[type=color]").cloneNode(true);colourInput.name="condition_color["+n+"]";colourCell.appendChild(colourInput);row.scrollIntoView({block:"nearest"})});if(remove)remove.addEventListener("click",function(){if(body.rows.length>1)body.deleteRow(-1)});const renumber=()=>Array.from(body.rows).forEach((r,i)=>Array.from(r.querySelectorAll("input")).forEach((x,j)=>x.name=(j===0?"fico_threshold[":j===1?"fico_points[":"condition_color[")+i+"]"));const decorate=()=>{Array.from(body.rows).forEach(r=>{if(false){const c=r.insertCell();c.style.whiteSpace="nowrap";for(const [label,delta] of [["↑",-1],["↓",1]]){const b=document.createElement("button");b.type="button";b.textContent=label;b.title=delta<0?"Move up":"Move down";b.className="fico-move";b.setAttribute("aria-label",b.title);b.addEventListener("click",()=>{const target=delta<0?r.previousElementSibling:r.nextElementSibling;if(!target)return;if(delta<0)body.insertBefore(r,target);else body.insertBefore(target,r);renumber()});c.appendChild(b)}}});renumber()};decorate();if(add)add.addEventListener("click",decorate);const sort=document.getElementById("fico-sort-rows");if(sort)sort.addEventListener("click",()=>{Array.from(body.rows).sort((a,b)=>Number(b.cells[0].querySelector("input").value)-Number(a.cells[0].querySelector("input").value)).forEach(r=>body.appendChild(r));renumber()});const form=body.closest("form");if(form)form.addEventListener("submit",renumber);});</script>';
        $psbDialog = '<dialog id="psb-formula-dialog" style="border:1px solid #b8c9dd;border-radius:12px;max-width:610px;width:95%;max-height:90vh;overflow:auto;padding:24px;box-shadow:0 16px 60px #0004"><h3>PSB · Collection Fails /100 stops</h3><p class="muted">Each row awards its points when PSB is at or below that threshold. The lowest matching threshold applies; above the highest threshold, 0 points. Your existing rule is preserved until you save changes.</p>';
        if ($full) $psbDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="psb_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('psb_rules')->getValue()).'">';
        $psbDialog .= '<h4>Scoring conditions</h4><div style="max-height:32vh;overflow:auto;border:1px solid #d5e0ec;border-radius:9px"><table><thead><tr><th>PSB at or below</th><th>Points / '.$escape((string)$scoreRules['psb']['maximum']).'</th><th>Colour</th></tr></thead><tbody id="psb-bands-body">';
        foreach ($psbBands as $i => [$threshold, $points]) $psbDialog .= '<tr><td><input type="number" name="psb_threshold['.$i.']" min="0" step="any" required value="'.$escape((string)$threshold).'" style="width:130px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="psb_points['.$i.']" min="0" max="'.$escape((string)$scoreRules['psb']['maximum']).'" step="any" required value="'.$escape((string)$points).'" style="width:130px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['psb'][(string)(float)$threshold]).'" style="width:75px;height:42px;padding:4px;border:1px solid #b8c9dd;border-radius:7px"'.(!$full ? ' disabled' : '').'></td></tr>';
        $psbDialog .= '</tbody></table></div>';
        if ($full) $psbDialog .= '<div class="psb-actions"><button type="button" id="psb-add">＋ Add row</button><button type="button" id="psb-remove">− Remove last</button><button type="button" id="psb-sort">↕ Sort thresholds</button></div>';
        $psbDialog .= '<section hidden class="psb-colour-section"><h4>Conditional colours</h4><p class="muted">Colours are independent of scoring: PSB = 0 is green; PSB &gt; 0 is red.</p><div class="psb-colour-grid">';
        foreach (['green' => 'Green · PSB = 0', 'red' => 'Red · PSB > 0'] as $shade => $label) $psbDialog .= '<label>'.$label.'<input type="color" name="psb_'.$shade.'_color" value="'.$escape((string)$psbColors[$shade.'_color']).'"'.(!$full ? ' disabled' : '').'></label>';
        $psbDialog .= '</div></section>';
        if ($full) $psbDialog .= '<div class="psb-actions"><button type="submit" class="psb-save">Save PSB formula and colours</button><button type="button" onclick="document.getElementById(&quot;psb-formula-dialog&quot;).close()">Close</button></div></form>';
        else $psbDialog .= '<button type="button" onclick="document.getElementById(&quot;psb-formula-dialog&quot;).close()">Close</button>';
        $psbDialog .= '</dialog><script>(function(){const dlg=document.getElementById("psb-formula-dialog");if(!dlg)return;const body=dlg.querySelector("#psb-bands-body");const renumber=()=>Array.from(body.rows).forEach((row,i)=>row.querySelectorAll("input").forEach((input,j)=>input.name=(j===0?"psb_threshold[":j===1?"psb_points[":"condition_color[")+i+"]"));const add=dlg.querySelector("#psb-add"),remove=dlg.querySelector("#psb-remove"),sort=dlg.querySelector("#psb-sort");if(add)add.onclick=()=>{if(body.rows.length>=40)return;const row=body.rows[body.rows.length-1].cloneNode(true);row.cells[0].querySelector("input").value=Number(row.cells[0].querySelector("input").value)+1;row.cells[1].querySelector("input").value=0;body.appendChild(row);renumber()};if(remove)remove.onclick=()=>{if(body.rows.length>1)body.deleteRow(-1);renumber()};if(sort)sort.onclick=()=>{Array.from(body.rows).sort((a,b)=>Number(a.cells[0].querySelector("input").value)-Number(b.cells[0].querySelector("input").value)).forEach(row=>body.appendChild(row));renumber()};const form=dlg.querySelector("form");if(form)form.addEventListener("submit",renumber)})();</script>';
        foreach ($request->getSession()->getFlashBag()->get('psb_error') as $error) $psbDialog .= '<p role="alert" style="color:#a32929">'.$escape($error).'</p>';
        $ceDialog = '<dialog id="ce-formula-dialog" style="border:1px solid #b8c9dd;border-radius:12px;max-width:600px;width:95%;max-height:90vh;overflow:auto;padding:24px;box-shadow:0 16px 60px #0004"><h3>CE · Escalations scoring formula and colours</h3><p class="muted">Award points at or below each CE threshold, lowest matching first. The default is 15 points at zero, 0 points above zero. Colours are independent of scoring.</p>';
        if ($full) $ceDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="ce_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('ce_formula_rules')->getValue()).'">';
        $ceDialog .= '<h4>Scoring conditions</h4><div style="max-height:30vh;overflow:auto;border:1px solid #d5e0ec;border-radius:9px"><table><thead><tr><th>CE at or below</th><th>Points / '.$escape((string)$scoreRules['ce']['maximum']).'</th><th>Colour</th></tr></thead><tbody id="ce-bands-body">';
        foreach ($ceBands as $i => [$threshold,$points]) $ceDialog .= '<tr><td><input type="number" name="ce_threshold['.$i.']" min="0" step="any" required value="'.$escape((string)$threshold).'" style="width:125px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="ce_points['.$i.']" min="0" max="'.$escape((string)$scoreRules['ce']['maximum']).'" step="any" required value="'.$escape((string)$points).'" style="width:125px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['ce'][(string)(float)$threshold]).'" style="width:75px;height:42px;padding:4px;border:1px solid #b8c9dd;border-radius:7px"'.(!$full ? ' disabled' : '').'></td></tr>';
        $ceDialog .= '</tbody></table></div>';
        if ($full) $ceDialog .= '<div class="ce-actions"><button type="button" id="ce-add">＋ Add row</button><button type="button" id="ce-remove">− Remove last</button><button type="button" id="ce-sort">↕ Sort thresholds</button></div>';
        $ceDialog .= '<label style="display:block;margin:12px 0">Points above highest threshold<input type="number" name="ce_above_points" min="0" max="'.$escape((string)$scoreRules['ce']['maximum']).'" step="any" required value="'.$escape((string)$ceAbovePoints).'" style="display:block;width:125px;margin:5px 0"'.(!$full ? ' disabled' : '').'></label><section hidden class="ce-colour-section"><h4>Conditional colours</h4><p class="muted">Zero = green, positive CE = red, missing/dash = green. Choose colours below.</p><div class="ce-colour-preview"><span style="background:'.$escape((string)$ceColors['positive_color']).'">Red</span><span style="background:'.$escape((string)$ceColors['zero_color']).'">Green</span><span style="background:'.$escape((string)$ceColors['missing_color']).'">Missing</span></div><div class="ce-colour-grid">';
        foreach (['zero' => 'Zero (green)', 'positive' => 'Above zero (red)', 'missing' => 'Missing / dash (green)'] as $shade => $label) $ceDialog .= '<label>'.$label.'<input type="color" name="ce_'.$shade.'_color" value="'.$escape((string)$ceColors[$shade.'_color']).'"'.(!$full ? ' disabled' : '').'></label>';
        $ceDialog .= '</div></section>';
        if ($full) $ceDialog .= '<div class="ce-actions"><button type="submit" class="ce-save">Save CE formula and colours</button><button type="button" onclick="this.closest(&quot;dialog&quot;).close()">Close</button></div></form>';
        else $ceDialog .= '<button type="button" onclick="this.closest(&quot;dialog&quot;).close()">Close</button>';
        $ceDialog .= '</dialog><script>(function(){const dlg=document.getElementById("ce-formula-dialog");const body=dlg.querySelector("#ce-bands-body");const renumber=()=>Array.from(body.rows).forEach((row,i)=>row.querySelectorAll("input").forEach((input,j)=>input.name=(j===0?"ce_threshold[":j===1?"ce_points[":"condition_color[")+i+"]"));const add=dlg.querySelector("#ce-add"),remove=dlg.querySelector("#ce-remove"),sort=dlg.querySelector("#ce-sort");if(add)add.onclick=()=>{if(body.rows.length>=40)return;const row=body.rows[body.rows.length-1].cloneNode(true);row.cells[0].querySelector("input").value=Number(row.cells[0].querySelector("input").value)+1;row.cells[1].querySelector("input").value=0;body.appendChild(row);renumber()};if(remove)remove.onclick=()=>{if(body.rows.length>1)body.deleteRow(-1);renumber()};if(sort)sort.onclick=()=>{Array.from(body.rows).sort((a,b)=>Number(a.cells[0].querySelector("input").value)-Number(b.cells[0].querySelector("input").value)).forEach(row=>body.appendChild(row));renumber()};const form=dlg.querySelector("form");if(form)form.addEventListener("submit",renumber)})();</script>';
        $metricDialogs = '';
        foreach (['dpmo' => [$dpmoBands, $dpmoColors, 'DSC DPMO', 'dsc_dpmo'], 'pod' => [$podBands, $podColors, 'POD', 'pod'], 'cc' => [$ccBands, $ccColors, 'CC', 'cc'], 'cdf' => [$cdfBands, $cdfColors, 'Negative Feedback', 'cdf_dpmo']] as $metric => [$bands, $colors, $label, $category]) {
            $metricDialogs .= '<dialog id="'.$metric.'-formula-dialog" style="border:1px solid #b8c9dd;border-radius:12px;max-width:650px;width:95%;padding:24px;box-shadow:0 16px 60px #0004"><h3>'.$label.' scoring formula and colours</h3><p class="muted">Minimum thresholds are evaluated highest first (CC uses a strict &gt; 99.8% condition). Below the lowest threshold: 0 points unless a separate below-value rule matches. Colours are independently configurable.</p>';
            if ($full) $metricDialogs .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="metric_action" value="'.$metric.'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken($metric.'_formula_rules')->getValue()).'">';
            $metricDialogs .= '<div style="max-height:32vh;overflow:auto;border:1px solid #d5e0ec;border-radius:9px"><table><thead><tr><th>Minimum '.$label.(in_array($metric, ['pod','cc'], true) ? ' %' : '').'</th><th>Points / '.$escape((string)$scoreRules[$category]['maximum']).'</th><th>Colour</th></tr></thead><tbody>';
            foreach ($bands as $i => [$minimum, $awarded]) $metricDialogs .= '<tr><td><input type="number" name="metric_threshold['.$i.']" min="0" '.(in_array($metric, ['pod','cc'], true) ? 'max="100" ' : '').'step="any" required value="'.$escape((string)$minimum).'" style="width:120px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="metric_points['.$i.']" min="0" max="'.$escape((string)$scoreRules[$category]['maximum']).'" step="any" required value="'.$escape((string)$awarded).'" style="width:110px;margin:0"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours[$metric][(string)(float)$minimum]).'" style="width:75px;height:42px;padding:4px;border:1px solid #b8c9dd;border-radius:7px"'.(!$full ? ' disabled' : '').'></td></tr>';
            $metricDialogs .= '</tbody></table></div>';
            if ($full) $metricDialogs .= '<div class="metric-formula-actions"><button type="button" class="metric-add">＋ Add row</button><button type="button" class="metric-remove">− Remove last</button><button type="button" class="metric-sort">↕ Sort thresholds</button></div>';
            $low = $metric === 'cdf' ? $colors['green_below'] : ($metric !== 'dpmo' ? $colors['red_below'] : $colors['green_at_or_below']);
            $high = $metric === 'cdf' ? $colors['red_above'] : ($metric !== 'dpmo' ? $colors['green_above'] : $colors['red_above']);
            $metricDialogs .= '<div hidden class="metric-colour-section"><h4 style="margin:0 0 8px">Conditional colours</h4><p class="muted" style="margin:0 0 10px">Set colour thresholds and choose the colours shown in Weekly Reports.</p><div class="metric-colour-preview"><span style="background:'.$escape((string)$colors['red_color']).'">Red</span><span style="background:'.$escape((string)$colors['amber_color']).'">Amber</span><span style="background:'.$escape((string)$colors['green_color']).'">Green</span></div><div class="metric-colour-grid"><label>'.($metric === 'cdf' ? 'Green when points below' : ($metric !== 'dpmo' ? 'Red below (%)' : 'Green at or below')).'<input type="number" name="metric_low" min="0" '.(in_array($metric, ['pod','cc'], true) ? 'max="100" ' : '').'step="any" required value="'.$escape((string)$low).'"'.(!$full ? ' disabled' : '').'></label><label>'.($metric === 'cdf' ? 'Red when points above' : ($metric !== 'dpmo' ? 'Green above (%)' : 'Red above')).'<input type="number" name="metric_high" min="0" '.(in_array($metric, ['pod','cc'], true) ? 'max="100" ' : '').'step="any" required value="'.$escape((string)$high).'"'.(!$full ? ' disabled' : '').'></label></div><div class="metric-colour-grid">';
            foreach (['red' => 'Red', 'amber' => 'Amber', 'green' => 'Green'] as $shade => $shadeLabel) $metricDialogs .= '<label class="metric-swatch-label">'.$shadeLabel.' colour<input type="color" name="metric_'.$shade.'_color" value="'.$escape((string)$colors[$shade.'_color']).'"'.(!$full ? ' disabled' : '').'></label>';
            $metricDialogs .= '</div></div>';
            if ($metric === 'cdf') $metricDialogs .= '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0"><label>Below this feedback value (strict &lt;)<input type="number" name="cdf_below" min="0" step="any" value="'.$escape((string)$cdfBelow).'" required></label><label>Points below this value<input type="number" name="cdf_below_points" min="0" max="'.$escape((string)$scoreRules['cdf_dpmo']['maximum']).'" step="any" value="'.$escape((string)$cdfBelowPoints).'" required></label></div>';
            if ($full) $metricDialogs .= '<div class="metric-save-actions"><button type="submit">Save '.$label.' formula and colours</button><button type="button" onclick="this.closest(&quot;dialog&quot;).close()">Close</button></div></form>';
            $metricDialogs .= (!$full ? '<button type="button" class="metric-close" onclick="this.closest(&quot;dialog&quot;).close()">Close</button>' : '').'</dialog>';
        }
        $metricDialogs .= '<script>document.querySelectorAll("#dpmo-formula-dialog,#pod-formula-dialog,#cc-formula-dialog").forEach(dlg=>{const body=dlg.querySelector("tbody");const renumber=()=>Array.from(body.rows).forEach((r,i)=>r.querySelectorAll("input").forEach((x,j)=>x.name=(j===0?"metric_threshold[":j===1?"metric_points[":"condition_color[")+i+"]"));const add=dlg.querySelector(".metric-add"),remove=dlg.querySelector(".metric-remove"),sort=dlg.querySelector(".metric-sort");if(add)add.onclick=()=>{if(body.rows.length>=40)return;const r=body.rows[body.rows.length-1].cloneNode(true);r.querySelectorAll("input").forEach((x,i)=>x.value=x.type==="color"?x.value:(i===1?0:Math.max(0,Number(x.value)-1)));body.appendChild(r);renumber()};if(remove)remove.onclick=()=>{if(body.rows.length>1)body.deleteRow(-1);renumber()};if(sort)sort.onclick=()=>{Array.from(body.rows).sort((a,b)=>Number(b.cells[0].querySelector("input").value)-Number(a.cells[0].querySelector("input").value)).forEach(r=>body.appendChild(r));renumber()};const form=dlg.querySelector("form");if(form)form.addEventListener("submit",renumber);dlg.querySelectorAll("input[type=color]").forEach(input=>input.addEventListener("input",()=>{const name=input.name.split("_")[1];const preview=dlg.querySelector(".metric-colour-preview span:nth-child("+({red:1,amber:2,green:3}[name])+")");if(preview)preview.style.background=input.value}))});</script>';
        if (in_array($request->query->get('metric_saved'), ['dpmo','pod','cc'], true)) $metricDialogs .= '<p style="color:green">'.strtoupper((string)$request->query->get('metric_saved')).' formula saved.</p>';
        if ($full) {
            $rulesTabPanel .= '<div style="border:1px solid #cbd9e8;border-radius:10px;padding:18px;margin:14px 0;background:#f7fafc"><h3>Import report titles</h3><p class="muted">Each import must contain its matching title. Depot prefixes and week numbers can change. Matching is case-insensitive.</p>';
            if ($request->query->get('report_titles_saved') === '1') $rulesTabPanel .= '<p style="color:#17683f">Report titles saved.</p>';
            if ($request->query->get('report_titles_error') === '1') $rulesTabPanel .= '<p style="color:#a42323">Each title must be between 1 and 150 characters.</p>';
            $rulesTabPanel .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('report_titles')->getValue()).'"><input type="hidden" name="rules_action" value="report_titles"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">';
            foreach (['dsp'=>'DSP Daily Summary','cc'=>'Contact Compliance (CC)','pod'=>'Photo on Delivery (POD)','dwc'=>'DWC/IADC'] as $key=>$label) {
                $rulesTabPanel .= '<label style="display:flex;flex-direction:column;gap:6px;font-weight:600">'.$escape($label).'<input type="text" name="report_titles['.$key.']" value="'.$escape($reportTitles[$key]).'" maxlength="150" required style="padding:10px;border:1px solid #aebfd0;border-radius:6px"></label>';
            }
            $rulesTabPanel .= '</div><button type="submit" style="margin-top:14px;background:#17456c;color:#fff;border:0;border-radius:7px;padding:10px 18px;cursor:pointer">Save report titles</button></form></div>';
        }
        $rulesTabPanel .= '<div style="border:1px solid #cbd9e8;border-radius:10px;padding:18px;margin:14px 0;background:#f7fafc"><h3>Scorecard wording and recommended focus areas</h3><p class="muted">Edit the messages displayed when a driver needs improvement. Only applicable focus areas appear on their scorecard. Leave a message blank to hide it.</p>';
        if ($request->query->get('scorecard_text_saved') === '1') $rulesTabPanel .= '<p style="color:#17683f">Scorecard wording saved.</p>';
        if ($full) $rulesTabPanel .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('scorecard_text')->getValue()).'"><input type="hidden" name="rules_action" value="scorecard_text">';
        $rulesTabPanel .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px">';
        foreach ($focusMessages as $key => $message) $rulesTabPanel .= '<label style="display:flex;flex-direction:column;gap:5px;font-weight:700">'.$escape($key).'<textarea name="focus_messages['.$escape($key).']" rows="3" maxlength="1200" style="width:100%;padding:9px;border:1px solid #b9cbdc;border-radius:7px"'.(!$full?' readonly':'').'>'.$escape($message).'</textarea></label>';
        $rulesTabPanel .= '</div><label style="display:block;margin-top:14px;font-weight:700">Scorecard explanation (optional custom text)<textarea name="scorecard_explanation" rows="9" maxlength="12000" style="display:block;width:100%;margin-top:6px;padding:10px;border:1px solid #b9cbdc;border-radius:7px"'.(!$full?' readonly':'').'>'.$escape($scorecardExplanation).'</textarea></label>';
        if ($full) $rulesTabPanel .= '<button type="submit" style="margin-top:12px;background:#17456c;color:white;border:0;border-radius:7px;padding:10px 18px">Save scorecard wording</button></form>';
        $rulesTabPanel .= '</div>';
        $rulesTabPanel .= ($request->query->get('fico_formula_error') === 'duplicate' ? '<p style="color:#b42332">Duplicate minimum FICO scores. Please use different scores for each row.</p>' : '');
        $rulesTabPanel .= ($request->query->get('fico_formula_saved') === '1' ? '<p style="color:#17683f">FICO scoring formula saved.</p>' : '').'<h3>Scoring weights</h3>'.$rulesPanel.$formulaDialog.$dwcDialog.$psbDialog.$ceDialog.$metricDialogs.'</section>';

        $access = $full ? 'Full access' : 'Read only';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Driver Performance · Dulogix</title>'
            .SectionAccess::CSS
            .'<style>*{box-sizing:border-box}body{margin:0;background:#f1f5fa;color:#17314e;font:15px system-ui,sans-serif}header{background:#152d49;color:white;padding:14px 24px}header img{height:39px;background:white;padding:3px;border-radius:5px}main{max-width:1200px;margin:28px auto;padding:0 18px}h1{margin:0 0 8px}.muted{color:#65788e}.tabs{display:flex;gap:8px;flex-wrap:wrap;border-bottom:1px solid #d5e0ec;margin-top:25px}.tab{padding:13px 18px;border:1px solid #d5e0ec;border-bottom:0;border-radius:10px 10px 0 0;background:#e6edf6;color:#173b60;font-weight:700}.tab.active{background:white;color:#135c91}.tab[disabled]{opacity:.6;cursor:not-allowed}.card{background:white;border:1px solid #d5e0ec;border-radius:0 12px 12px 12px;padding:22px;box-shadow:0 7px 20px #17314e0b}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #e1e8f0}th{background:#eaf1f8}#performance-drivers{border:1px solid #b5c3d2;table-layout:auto}#performance-drivers thead tr:first-child th{background:#aebbc9;color:#102d48;text-align:center;border:1px solid #8395a8;padding:15px 10px;font-size:12px;white-space:nowrap}#performance-drivers thead tr:nth-child(2) th{background:#d8e1eb;color:#102d48;text-align:center;border:1px solid #a5b4c4;padding:8px 5px;min-width:65px;vertical-align:middle;font-size:13px;line-height:1.4}#performance-drivers thead tr:nth-child(2) th small{display:block;color:#bb2437;font-size:13px;font-weight:800;margin-top:5px}#performance-drivers thead tr:first-child th[rowspan]{background:#aebbc9}#performance-drivers thead tr:first-child th:first-child{min-width:190px}#performance-drivers thead tr:first-child th:nth-child(2){min-width:145px;width:145px}#performance-drivers thead tr:nth-child(2) th:first-child{min-width:70px;width:70px;max-width:70px}#performance-drivers td{border-right:1px solid #d6e0e9;vertical-align:middle}.metric-detail{position:relative}.metric-detail summary{cursor:pointer;white-space:nowrap}.metric-detail-panel{position:absolute;z-index:10;top:100%;left:0;background:white;border:1px solid #a5b4c4;box-shadow:0 5px 16px #102d4833;border-radius:8px;padding:12px 16px;min-width:430px;line-height:1.8}.netradyne-fields{display:flex;gap:8px;align-items:flex-end;margin:10px 0}.netradyne-fields label{font-size:12px;font-weight:700;text-align:center}.netradyne-fields input{width:65px;min-width:65px;margin:4px 0;padding:7px 4px;text-align:center}.metric-detail-panel button{background:#17456c;color:white;border:0;border-radius:6px;padding:8px 18px;cursor:pointer}#performance-drivers tbody tr:nth-child(even){background:#f5f8fc}input{font:inherit;padding:11px;border:1px solid #b9cbdc;border-radius:8px;width:100%;max-width:360px;margin:10px 0}#dwc-formula-dialog #dwc-add,#dwc-formula-dialog #dwc-remove,#dwc-formula-dialog #dwc-sort{border:1px solid #b7cce0;border-radius:8px;background:#edf4fb;color:#19486d;font:600 13px system-ui,sans-serif;padding:10px 12px;cursor:pointer}#dwc-formula-dialog #dwc-add{background:#17456c;color:white;border-color:#17456c}#dwc-formula-dialog #dwc-remove{background:#fff4f4;color:#9b303a;border-color:#efcbd0}#dwc-formula-dialog .dwc-up,#dwc-formula-dialog .dwc-down{border:1px solid #c5d5e5;border-radius:7px;background:#f0f5fb;color:#17456c;font-size:16px;font-weight:700;min-width:32px;height:34px;margin-right:4px;cursor:pointer}#dwc-formula-dialog button:hover{filter:brightness(.94)}#dwc-formula-dialog button:focus-visible{outline:3px solid #7cb6e7;outline-offset:2px}#fico-formula-dialog .fico-formula-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}#fico-formula-dialog .fico-action{border:1px solid #b7cce0;border-radius:8px;background:#edf4fb;color:#19486d;font:600 13px system-ui,sans-serif;padding:10px 12px;cursor:pointer}#fico-formula-dialog .fico-action:hover{background:#dcebf9}#fico-formula-dialog .fico-action-add{background:#17456c;color:white;border-color:#17456c}#fico-formula-dialog .fico-action-add:hover{background:#23628f}#fico-formula-dialog .fico-action-remove{background:#fff4f4;color:#9b303a;border-color:#efcbd0}#fico-formula-dialog .fico-action-remove:hover{background:#ffe5e7}#fico-formula-dialog .fico-move{border:1px solid #c5d5e5;border-radius:7px;background:#f0f5fb;color:#17456c;font-size:16px;font-weight:700;min-width:32px;height:34px;margin-right:4px;cursor:pointer}#fico-formula-dialog .fico-move:hover{background:#d9e9f8}#fico-formula-dialog .fico-move:focus-visible,#fico-formula-dialog .fico-action:focus-visible{outline:3px solid #7cb6e7;outline-offset:2px}#dpmo-formula-dialog .metric-colour-grid,#pod-formula-dialog .metric-colour-grid,#cc-formula-dialog .metric-colour-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px;margin:10px 0}#dpmo-formula-dialog .metric-colour-grid label,#pod-formula-dialog .metric-colour-grid label,#cc-formula-dialog .metric-colour-grid label{display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:650}#dpmo-formula-dialog .metric-colour-grid input,#pod-formula-dialog .metric-colour-grid input,#cc-formula-dialog .metric-colour-grid input{width:100%;max-width:none;margin:0;padding:8px}#dpmo-formula-dialog input[type=color],#pod-formula-dialog input[type=color],#cc-formula-dialog input[type=color]{height:45px;cursor:pointer;appearance:auto;padding:5px;background:#edf4fb}#dpmo-formula-dialog input[type=color]::-webkit-color-swatch,#pod-formula-dialog input[type=color]::-webkit-color-swatch,#cc-formula-dialog input[type=color]::-webkit-color-swatch{border:1px solid #9cb1c6;border-radius:3px}#dpmo-formula-dialog .metric-colour-preview,#pod-formula-dialog .metric-colour-preview,#cc-formula-dialog .metric-colour-preview{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}#dpmo-formula-dialog .metric-colour-preview span,#pod-formula-dialog .metric-colour-preview span,#cc-formula-dialog .metric-colour-preview span{border-radius:7px;padding:9px 18px;color:#17314e;font-weight:700}#dpmo-formula-dialog .metric-formula-actions,#pod-formula-dialog .metric-formula-actions,#cc-formula-dialog .metric-formula-actions{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0 10px}#dpmo-formula-dialog .metric-formula-actions button,#pod-formula-dialog .metric-formula-actions button,#cc-formula-dialog .metric-formula-actions button,#dpmo-formula-dialog .metric-close,#pod-formula-dialog .metric-close,#cc-formula-dialog .metric-close{border:1px solid #b7cce0;border-radius:8px;background:#edf4fb;color:#19486d;font:600 13px system-ui,sans-serif;padding:10px 12px;cursor:pointer}#dpmo-formula-dialog .metric-add,#pod-formula-dialog .metric-add,#cc-formula-dialog .metric-add{background:#17456c!important;color:white!important}#dpmo-formula-dialog .metric-remove,#pod-formula-dialog .metric-remove,#cc-formula-dialog .metric-remove{background:#fff4f4!important;color:#9b303a!important}#dpmo-formula-dialog button[type=submit],#pod-formula-dialog button[type=submit],#cc-formula-dialog button[type=submit]{background:#17456c;color:white;border:0;border-radius:8px}#dpmo-formula-dialog tbody input,#pod-formula-dialog tbody input,#cc-formula-dialog tbody input{margin:0}#dpmo-formula-dialog .metric-close,#pod-formula-dialog .metric-close,#cc-formula-dialog .metric-close{background:#f1f5fa}.pill{background:#e3f2fc;color:#18577f;border-radius:99px;padding:6px 12px;font-size:13px;font-weight:700}@media(max-width:600px){th,td{padding:9px 6px;font-size:13px}}</style></head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a>'
            .SectionAccess::navigation($this->getUser())
            .'</header><main><h1>Driver Performance</h1><p class="muted">Performance Hub · Weekly driver reports</p><span class="pill">'.$access.'</span>'
            .'<div class="weekbar" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:white;border:1px solid #d5e0ec;border-radius:12px;padding:15px;margin-top:20px"><a id="week-prev" href="#" aria-label="Previous week" style="padding:9px 13px;border:1px solid #b8c9dd;border-radius:7px;text-decoration:none">◀</a><strong id="performance-week" style="min-width:210px;text-align:center"></strong><a id="week-next" href="#" aria-label="Next week" style="padding:9px 13px;border:1px solid #b8c9dd;border-radius:7px;text-decoration:none">▶</a><label for="week-calendar">Jump to week:</label><input id="week-calendar" type="date" aria-label="Choose a date within the week" style="width:auto;margin:0"></div>'
            .'<div class="tabs" role="tablist" aria-label="Performance Hub sections"><a class="tab'.($tab === 'weekly' ? ' active' : '').'" href="/driver-performance?tab=weekly&amp;week='.$escape($weekStart).'">Weekly Reports</a><a class="tab'.($tab === 'dsp' ? ' active' : '').'" href="/driver-performance?tab=dsp&amp;week='.$escape($weekStart).'">DSP Daily Summary</a><a class="tab'.($tab === 'cc' ? ' active' : '').'" href="/driver-performance?tab=cc&amp;week='.$escape($weekStart).'">CC (Contact Compliance)</a><a class="tab'.($tab === 'pod' ? ' active' : '').'" href="/driver-performance?tab=pod&amp;week='.$escape($weekStart).'">POD (Photo on Delivery)</a><a class="tab'.($tab === 'dwc' ? ' active' : '').'" href="/driver-performance?tab=dwc&amp;week='.$escape($weekStart).'">DWC/IADC Report</a><a class="tab'.($tab === 'rules' ? ' active' : '').'" href="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'">Rules</a></div>'
            .'<section class="card"'.($tab !== 'weekly' ? ' hidden' : '').'><h2>Weekly Reports</h2><label for="driver-search">Search drivers</label><div class="weekly-search-tools"><input id="driver-search" type="search" placeholder="Search by driver name">'.$topFivePanel.'</div><p class="muted">Total drivers: '.$count.' · Imported DSP rows: '.$dspCount.'</p><div style="overflow:visible"><table id="performance-drivers" style="width:100%;table-layout:auto"><colgroup><col style="width:160px"><col style="width:145px"><col><col><col><col><col><col><col style="width:102px"><col style="width:58px"><col></colgroup><thead><tr><th rowspan="2"><label class="driver-select-label driver-select-all-label"><input id="select-all-drivers" type="checkbox" aria-label="Select all visible drivers"><span>Driver name</span></label></th><th rowspan="2" style="min-width:90px;width:95px"><div>Rank</div><div>Status</div><div>Total Score</div></th><th colspan="2">Safety</th><th>Deliveries</th><th>Collections</th><th>Concessions</th><th style="white-space:normal;line-height:1.25">Picture on<br>Delivery</th><th title="Contact Compliance" style="padding:6px 2px;white-space:nowrap;min-width:0;font-size:12px">CC</th><th style="padding:6px 3px;white-space:normal;min-width:0;font-size:12px">Escalations</th><th>Negative Feedback</th></tr><tr><th style="width:70px;min-width:70px;max-width:70px">Ementor<br>Score <small>20%</small></th><th style="min-width:72px;width:78px;max-width:78px">Netradyne<br>Violation<br>Deductions</th><th style="min-width:78px;width:85px;max-width:85px">Delivery<br>Workflow<br>Compliance <small>10%</small></th><th style="min-width:78px;width:85px;max-width:85px">PSB<br>Collection Fails<br>/100 stops <small>5%</small></th><th style="min-width:92px;width:92px">DSC DPMO <small>20%</small></th><th>POD <small>10%</small></th><th style="width:1%;min-width:0;padding:5px 2px">CC <small>15%</small></th><th style="width:1%;min-width:0;padding:5px 2px">CE <small>15%</small></th><th>Negative Feedback <small>5%</small></th></tr></thead><tbody>'.$weeklyRows.'</tbody></table></div></section>'.$ccPanel.$podPanel.$dspPanel.$dwcPanel.$rulesTabPanel.'</main><script>document.getElementById("driver-search").addEventListener("input",function(){const q=this.value.toLowerCase().trim();document.querySelectorAll("#performance-drivers tbody tr").forEach(r=>r.hidden=!r.cells[0].textContent.toLowerCase().includes(q));});</script><script>(function(){const day=86400000;const parts=Object.fromEntries(new Intl.DateTimeFormat("en-GB",{timeZone:"Europe/London",year:"numeric",month:"2-digit",day:"2-digit"}).formatToParts(new Date()).filter(p=>p.type!=="literal").map(p=>[p.type,Number(p.value)]));const today=new Date(Date.UTC(parts.year,parts.month-1,parts.day));function monday(d){const m=new Date(d);m.setUTCDate(m.getUTCDate()-(m.getUTCDay()+6)%7);return m;}function iso(d){return d.toISOString().slice(0,10)}function weekNumber(d){const t=new Date(d);t.setUTCDate(t.getUTCDate()+4-(t.getUTCDay()||7));const y=new Date(Date.UTC(t.getUTCFullYear(),0,1));return Math.ceil((((t-y)/day)+1)/7)}function fmt(d){return d.toLocaleDateString("en-GB",{day:"numeric",month:"short",timeZone:"UTC"})}const params=new URLSearchParams(location.search);const raw=params.get("week");const valid=raw&&/^\d{4}-\d{2}-\d{2}$/.test(raw);let selected=valid?new Date(raw+"T00:00:00Z"):today;if(Number.isNaN(selected.getTime()))selected=today;selected=monday(selected);function render(){const sun=new Date(selected.getTime()+6*day);document.getElementById("performance-week").textContent="Week "+weekNumber(selected)+" · "+fmt(selected)+" – "+fmt(sun)+" "+sun.getUTCFullYear();document.getElementById("week-calendar").value=iso(selected);document.querySelectorAll(".tabs a").forEach(a=>{const u=new URL(a.href);u.searchParams.set("week",iso(selected));a.href=u.pathname+u.search});params.set("week",iso(selected));history.replaceState(null,"",location.pathname+"?"+params.toString());}function move(n){selected=new Date(selected.getTime()+n*7*day);navigate()}function navigate(){params.set("week",iso(selected));location.assign(location.pathname+"?"+params.toString())}document.getElementById("week-prev").addEventListener("click",e=>{e.preventDefault();move(-1)});document.getElementById("week-next").addEventListener("click",e=>{e.preventDefault();move(1)});document.getElementById("week-calendar").addEventListener("change",e=>{if(e.target.value){selected=monday(new Date(e.target.value+"T00:00:00Z"));navigate()}});render()})();</script></body></html>';
        $html = str_replace('</body>', '<script>const dz=document.getElementById("cc-drop");if(dz){["dragenter","dragover"].forEach(n=>dz.addEventListener(n,e=>{e.preventDefault();dz.style.background="#eaf2fc"}));["dragleave","drop"].forEach(n=>dz.addEventListener(n,e=>{e.preventDefault();dz.style.background=""}));dz.addEventListener("drop",e=>{if(e.dataTransfer.files.length)document.getElementById("cc-file").files=e.dataTransfer.files})}</script></body>', $html);
        $html = str_replace('</body>', '<script>(function(){const panels=document.querySelectorAll("#performance-drivers details.metric-detail");document.addEventListener("toggle",function(e){const active=e.target;if(!active.matches || !active.matches("#performance-drivers details.metric-detail") || !active.open)return;panels.forEach(p=>{if(p!==active)p.open=false})},true);document.addEventListener("click",function(e){if(e.target.closest("#performance-drivers details.metric-detail"))return;panels.forEach(p=>p.open=false)});})();</script></body>', $html);
        $html = str_replace('</body>', '<script>document.querySelectorAll("dialog").forEach(dialog=>{dialog.addEventListener("click",event=>{if(event.target!==dialog)return;const rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dialog.close()})});</script></body>', $html);
        $html = str_replace('</head>', '<style> #fico-formula-dialog{max-width:650px!important;width:min(650px,94vw)!important;max-height:90vh;overflow-y:auto;box-sizing:border-box} #fico-formula-dialog .fico-colour-editor{padding:18px!important;background:#f7fafd;border-radius:12px} #fico-formula-dialog .fico-colour-editor>div:first-of-type{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px!important;margin:12px 0 18px!important} #fico-formula-dialog .fico-colour-editor>div:first-of-type span{display:flex;align-items:center;justify-content:center;padding:10px 6px!important;text-align:center;font-size:12px;line-height:1.4;min-height:42px} #fico-formula-dialog .fico-colour-editor form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:14px;row-gap:12px;align-items:end} #fico-formula-dialog .fico-colour-editor form>label{display:flex!important;flex-direction:column!important;gap:6px!important;min-width:0;font-size:13px;font-weight:600} #fico-formula-dialog .fico-colour-editor form>label input{box-sizing:border-box;width:100%!important;max-width:100%!important;margin:0!important;min-width:0} #fico-formula-dialog .fico-colour-pickers{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:4px} #fico-formula-dialog .fico-colour-pickers label{display:flex!important;flex-direction:column!important;gap:6px!important;min-width:0;font-size:13px;font-weight:600} #fico-formula-dialog input[type=color]{display:block!important;width:100%!important;min-width:0!important;max-width:100%!important;height:48px!important;box-sizing:border-box;padding:5px!important;border:1px solid #bdcfe2;border-radius:8px;background:white;cursor:pointer} #fico-formula-dialog .fico-colour-editor form>button{grid-column:1/-1;justify-self:start;margin-top:4px} #dwc-formula-dialog .dwc-colour-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:12px 0} #dwc-formula-dialog .dwc-colour-grid label{display:flex;flex-direction:column;gap:6px;min-width:0} #dwc-formula-dialog .dwc-colour-grid input{width:100%;box-sizing:border-box;min-width:0;margin:0} #dwc-formula-dialog input[type=color],#psb-formula-dialog input[type=color]{height:45px;padding:5px;border:1px solid #bdcfe2;border-radius:8px;cursor:pointer} @media(max-width:480px){#fico-formula-dialog .fico-colour-editor form{grid-template-columns:1fr}#fico-formula-dialog .fico-colour-pickers{grid-template-columns:repeat(3,minmax(0,1fr))}#fico-formula-dialog .fico-colour-editor>div:first-of-type{grid-template-columns:1fr}} </style></head>', $html);
        $html = str_replace('</head>', '<style>#dpmo-formula-dialog .metric-colour-section,#pod-formula-dialog .metric-colour-section,#cc-formula-dialog .metric-colour-section{background:#f7fafd;border:1px solid #d5e0ec;border-radius:10px;padding:15px;margin:14px 0}#dpmo-formula-dialog .metric-save-actions,#pod-formula-dialog .metric-save-actions,#cc-formula-dialog .metric-save-actions{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 0}#dpmo-formula-dialog .metric-save-actions button,#pod-formula-dialog .metric-save-actions button,#cc-formula-dialog .metric-save-actions button{border-radius:8px;padding:11px 14px;border:1px solid #bdcfe2;background:#edf4fb;color:#19486d;font-weight:650;cursor:pointer}#dpmo-formula-dialog .metric-save-actions button[type=submit],#pod-formula-dialog .metric-save-actions button[type=submit],#cc-formula-dialog .metric-save-actions button[type=submit]{background:#17456c;color:white;border-color:#17456c}</style></head>', $html);
        $html = str_replace('</head>', '<style>#psb-formula-dialog .psb-actions{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}#psb-formula-dialog .psb-actions button{padding:10px 12px;border:1px solid #bfd0e1;border-radius:8px;background:#edf4fb;color:#19486d;font-weight:650;cursor:pointer}#psb-formula-dialog .psb-actions .psb-save,#psb-formula-dialog #psb-add{background:#17456c;color:white}#psb-formula-dialog #psb-remove{background:#fff4f4;color:#a02c36}#psb-formula-dialog .psb-colour-section{background:#f7fafd;border:1px solid #d5e0ec;border-radius:9px;padding:15px;margin-top:16px}#psb-formula-dialog .psb-colour-section h4{margin:0 0 8px}#psb-formula-dialog .psb-colour-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}#psb-formula-dialog .psb-colour-grid label{display:flex;flex-direction:column;gap:7px;min-width:0;font-weight:650}#psb-formula-dialog input[type=color]{width:100%;max-width:none;height:48px;margin:0;padding:5px;border:1px solid #bdcfe2;border-radius:8px;cursor:pointer}</style></head>', $html);
        $html = str_replace('</head>', '<style>#performance-drivers thead tr:first-child th:nth-child(10),#performance-drivers thead tr:first-child th:nth-child(11){padding:6px 2px!important;white-space:normal!important;min-width:0!important;font-size:12px!important;line-height:1.15}#performance-drivers thead tr:nth-child(2) th:nth-child(7),#performance-drivers thead tr:nth-child(2) th:nth-child(8){min-width:0!important;width:1%!important;padding:5px 2px!important}#performance-drivers tbody td:nth-child(9),#performance-drivers tbody td:nth-child(10){width:1%;min-width:0;white-space:nowrap;padding:5px 3px!important}#performance-drivers{table-layout:auto!important}#performance-drivers thead tr:first-child th:first-child{min-width:160px!important;width:160px!important}#performance-drivers thead tr:first-child th:nth-child(2){min-width:145px!important;width:145px!important}#performance-drivers thead tr:first-child th:nth-child(10){width:1%!important;white-space:normal!important}#performance-drivers thead tr:nth-child(2) th:nth-child(7){width:1%!important;min-width:0!important}#performance-drivers tbody td:nth-child(9){width:102px!important;white-space:nowrap!important;padding:5px 3px!important}#performance-drivers thead tr:first-child th:nth-child(10){width:102px!important;max-width:102px!important;overflow-wrap:normal}#performance-drivers colgroup col:nth-child(9){width:102px!important}#performance-drivers colgroup col:nth-child(10){width:58px!important}#performance-drivers thead tr:nth-child(2) th:nth-child(7),#performance-drivers tbody td:nth-child(9){max-width:none}</style></head>', $html);
        $html = str_replace('</head>', '<style>#ce-formula-dialog .ce-actions{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}#ce-formula-dialog .ce-actions button{padding:10px 12px;border:1px solid #bfd0e1;border-radius:8px;background:#edf4fb;color:#19486d;font-weight:650;cursor:pointer}#ce-formula-dialog .ce-actions .ce-save,#ce-formula-dialog #ce-add{background:#17456c;color:white}#ce-formula-dialog .ce-colour-section{background:#f7fafd;border:1px solid #d5e0ec;border-radius:9px;padding:15px;margin-top:16px}#ce-formula-dialog .ce-colour-preview{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}#ce-formula-dialog .ce-colour-preview span{padding:10px 16px;border-radius:7px;font-weight:700;color:#17314e}#ce-formula-dialog .ce-colour-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}#ce-formula-dialog .ce-colour-grid label{display:flex;flex-direction:column;gap:7px;min-width:0;font-weight:650;font-size:13px}#ce-formula-dialog input[type=color]{width:100%;height:45px;margin:0;padding:5px;border:1px solid #bdcfe2;border-radius:8px}</style></head>', $html);
        $html = str_replace('</head>', '<style>#dpmo-formula-dialog .metric-colour-section,#pod-formula-dialog .metric-colour-section,#cc-formula-dialog .metric-colour-section,#ce-formula-dialog .ce-colour-section{padding:20px!important;background:#f7fafd!important;border:1px solid #d5e0ec!important;border-radius:12px!important}#dpmo-formula-dialog .metric-colour-preview,#pod-formula-dialog .metric-colour-preview,#cc-formula-dialog .metric-colour-preview,#ce-formula-dialog .ce-colour-preview{display:flex!important;gap:10px!important;margin:16px 0!important}#dpmo-formula-dialog .metric-colour-grid,#pod-formula-dialog .metric-colour-grid,#cc-formula-dialog .metric-colour-grid,#ce-formula-dialog .ce-colour-grid{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(135px,1fr))!important;gap:14px!important;margin:14px 0!important}#dpmo-formula-dialog .metric-colour-grid label,#pod-formula-dialog .metric-colour-grid label,#cc-formula-dialog .metric-colour-grid label,#ce-formula-dialog .ce-colour-grid label{display:flex!important;flex-direction:column!important;gap:7px!important;font-weight:600!important}#dpmo-formula-dialog .metric-colour-grid input,#pod-formula-dialog .metric-colour-grid input,#cc-formula-dialog .metric-colour-grid input,#ce-formula-dialog .ce-colour-grid input{width:100%!important;max-width:none!important;margin:0!important;box-sizing:border-box!important}#dpmo-formula-dialog input[type=color],#pod-formula-dialog input[type=color],#cc-formula-dialog input[type=color],#ce-formula-dialog input[type=color]{display:block!important;width:100%!important;height:61px!important;padding:10px!important;border:1px solid #bfd0e1!important;border-radius:10px!important;background:#edf4fb!important;cursor:pointer!important}</style></head>', $html);
        $html = str_replace('</head>', '<style>#cdf-formula-dialog .metric-formula-actions{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}#cdf-formula-dialog .metric-formula-actions button,#cdf-formula-dialog .metric-save-actions button{padding:10px 13px;border-radius:8px;border:1px solid #b8c9dd;cursor:pointer}#cdf-formula-dialog .metric-save-actions{display:flex;gap:10px;margin-top:18px}#cdf-formula-dialog .metric-save-actions button[type=submit],#cdf-formula-dialog .metric-add{background:#17456c;color:white}#cdf-formula-dialog tbody input[type=color]{width:76px;height:42px;padding:5px;border:1px solid #b8c9dd;border-radius:7px}</style></head>', $html);
        $html = str_replace('</head>', '<style>main section.card:not([hidden]) table:not(dialog table) thead th{position:sticky;top:0;z-index:5;background:#eaf1f8;background-clip:padding-box}#performance-drivers{border-collapse:separate;border-spacing:0}#performance-drivers thead tr:first-child th{top:0;z-index:6}#performance-drivers thead tr:nth-child(2) th{top:var(--performance-header-top,68px);z-index:5}#performance-drivers thead tr:first-child th[rowspan]{z-index:7}</style></head>', $html);
        $html = str_replace('</body>', '<script>(function(){const t=document.getElementById("performance-drivers");if(!t)return;const row=t.tHead.rows[0];const update=()=>t.style.setProperty("--performance-header-top",row.getBoundingClientRect().height+"px");update();window.addEventListener("resize",update);if(window.ResizeObserver)new ResizeObserver(update).observe(row)})();</script></body>', $html);
        $html = str_replace('</body>', '<script>(function(){const table=document.getElementById("performance-drivers");if(!table)return;table.querySelectorAll("tbody tr").forEach(row=>{Array.from(row.cells).slice(2).forEach(cell=>{if(cell.classList.contains("metric-three-lines"))return;const nodes=Array.from(cell.childNodes);const top=document.createElement("div"),middle=document.createElement("div"),bottom=document.createElement("div");top.className="metric-line-weight";middle.className="metric-line-value";bottom.className="metric-line-extra";const first=nodes.find(n=>n.nodeType===1||n.nodeType===3&&n.textContent.trim());if(first&&first.nodeType===1&&first.tagName==="DIV"&&first.textContent.trim().match(/^[0-9.]+%$/)){top.appendChild(first)}else if(first&&first.nodeType===1&&first.tagName==="DIV"&&first.style.color&&first.textContent.trim().endsWith("%")){top.appendChild(first)}const extra=nodes.find(n=>n.nodeType===1&&n.tagName==="DIV"&&n.textContent.trim().startsWith("Qty ="));if(extra)bottom.appendChild(extra);while(cell.firstChild)middle.appendChild(cell.firstChild);cell.append(top,middle,bottom);cell.classList.add("metric-three-lines")})})})();</script></body>', $html);
        $html = str_replace('</head>', '<style>#performance-drivers tbody td.metric-three-lines{padding:5px 3px!important;vertical-align:middle!important;text-align:center!important}#performance-drivers .metric-line-weight{height:19px;display:flex;align-items:center;justify-content:center;color:#bb2437;font-size:12px;font-weight:800}#performance-drivers .metric-line-value{height:38px;display:flex;align-items:center;justify-content:center;min-width:0}#performance-drivers .metric-line-extra{height:19px;display:flex;align-items:center;justify-content:center;white-space:nowrap;font-size:12px}#performance-drivers .metric-line-value details{max-width:100%}#performance-drivers .metric-line-value summary{display:flex;align-items:center;gap:3px}#performance-drivers .metric-line-value>span{margin:0}#performance-drivers .metric-line-value .metric-detail-panel{display:block}</style></head>', $html);
        $html = str_replace('</head>', '<style>#performance-drivers .driver-select-label{display:flex;align-items:center;gap:8px;cursor:pointer;text-align:left;min-width:0}#performance-drivers .driver-select-label span{min-width:0;overflow-wrap:anywhere}#performance-drivers .driver-select-all-label{justify-content:center;font-weight:700}#performance-drivers input.driver-row-select,#performance-drivers input#select-all-drivers{appearance:auto;width:17px!important;height:17px!important;min-width:17px;max-width:17px;margin:0!important;padding:0!important;flex:none;accent-color:#17456c;cursor:pointer}#performance-drivers tbody tr.driver-selected{box-shadow:inset 3px 0 #2671b0}</style></head>', $html);
        $html = str_replace('</body>', '<script>(function(){const table=document.getElementById("performance-drivers"),all=document.getElementById("select-all-drivers");if(!table||!all)return;const boxes=Array.from(table.querySelectorAll("tbody .driver-row-select"));function visible(){return boxes.filter(b=>!b.closest("tr").hidden)}function update(){const v=visible(),n=v.filter(b=>b.checked).length;all.checked=v.length>0&&n===v.length;all.indeterminate=n>0&&n<v.length;all.disabled=v.length===0;boxes.forEach(b=>b.closest("tr").classList.toggle("driver-selected",b.checked))}all.addEventListener("change",()=>{visible().forEach(b=>b.checked=all.checked);update()});boxes.forEach(b=>b.addEventListener("change",update));document.getElementById("driver-search").addEventListener("input",update);update()})();</script></body>', $html);
        $html = str_replace('</head>', '<style>.weekly-search-tools{display:flex;align-items:center;gap:24px;flex-wrap:wrap}.weekly-search-tools input{max-width:540px}.top-five-button{background:#17456c;color:white;border:0;border-radius:9px;padding:13px 22px;font-weight:700;cursor:pointer;min-height:48px}.top-five-button:hover{background:#24638e}.top-five-dialog{border:1px solid #c8d9e9;border-radius:14px;box-shadow:0 18px 55px #102a4560;padding:0;width:min(540px,calc(100vw - 32px));max-height:85vh;color:#17314e}.top-five-dialog::backdrop{background:#102a4570}.top-five-heading{display:flex;align-items:flex-start;justify-content:space-between;background:#17456c;color:white;padding:18px 20px;gap:16px}.top-five-heading strong{font-size:22px}.top-five-week{font-size:14px;margin-top:5px;opacity:.9}.top-five-heading-actions{display:flex;align-items:center;gap:12px}.top-five-logo{max-width:110px;max-height:48px;object-fit:contain;background:white;border-radius:6px;padding:4px}.top-five-heading button{background:transparent;color:white;border:0;font-size:23px;cursor:pointer}.top-five-list{padding:12px 16px 8px}.top-five-share{display:flex;gap:10px;flex-wrap:wrap;padding:12px 18px}.top-five-share button{border:0;border-radius:8px;padding:11px 15px;background:#17456c;color:white;font-weight:700;cursor:pointer}.top-five-share button:last-child{background:#16834b}.top-five-feedback{font-size:12px;color:#58708a;margin:0 18px 14px}.top-five-entry{display:grid;grid-template-columns:48px minmax(0,1fr) auto auto;align-items:center;gap:10px;padding:12px 5px;border-bottom:1px solid #e1eaf3}.top-five-entry:last-child{border:0}.top-five-trophy{font-size:28px;position:relative;display:inline-flex;justify-content:center}.top-five-trophy small{position:absolute;bottom:-5px;right:1px;background:#17456c;color:white;border-radius:50%;font-size:10px;min-width:15px;text-align:center}.top-five-name{font-weight:700;overflow-wrap:anywhere}.top-five-score{font-weight:700;color:#087a39;white-space:nowrap}.top-five-rank{font-size:12px;color:#58708a;white-space:nowrap}@media(max-width:560px){.top-five-entry{grid-template-columns:36px minmax(0,1fr) auto}.top-five-rank{display:none}}</style></head>', $html);
        $html = str_replace('</body>', '<script src="/top-five-share.js?v=1"></script></body>', $html);
        $scorecardConfig = json_encode(['focus' => $focusMessages, 'explanation' => $scorecardExplanation], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $html = str_replace('</body>', '<script>window.driverScorecardConfig='.$scorecardConfig.';</script></body>', $html);
        $html = str_replace('</body>', '<dialog id="driver-scorecard-dialog" class="driver-scorecard-dialog"><div id="driver-scorecard-content"></div><div class="scorecard-actions"><button type="button" id="driver-scorecard-print">Print / Save PDF</button><button type="button" id="driver-scorecard-share-image">Share image on WhatsApp</button><button type="button" id="driver-scorecard-share">Share PDF on WhatsApp</button><button type="button" id="driver-scorecard-close">Close</button></div></dialog><script src="/driver-scorecard.js?v=20261103-18"></script><script src="/dulogix-image-share.js?v=1"></script><script src="/rota-share.js?v=3"></script><script src="/vendor-html2canvas.min.js?v=1"></script><script>window.scorecardDistributionConfig={week:'.json_encode($weekStart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).',csrf:'.json_encode($csrfManager->getToken('scorecard_distribution')->getValue(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).'};</script><script src="/vendor-jspdf.min.js?v=1"></script><script src="/driver-scorecard-share.js?v=13"></script><script src="/driver-scorecard-distribution.js?v=4"></script></body>', $html);
        $html = str_replace('</head>', '<style>.driver-report-open{display:block;margin:8px auto 0;border:1px solid #b5cde2;background:#edf5fc;color:#17456c;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:800;cursor:pointer}.driver-report-open:hover{background:#d9eafb}.driver-scorecard-dialog{width:min(820px,calc(100vw - 24px));max-height:90vh;border:1px solid #c4d4e3;border-radius:12px;padding:0;box-shadow:0 20px 65px #10263e66;color:#16334e}.driver-scorecard-dialog::backdrop{background:#10263e99}.scorecard-actions{display:flex;justify-content:flex-end;gap:10px;padding:12px 24px;border-top:1px solid #dce6ef}.scorecard-actions button{background:#17456c;color:white;border:0;border-radius:7px;padding:10px 16px;cursor:pointer}@media print{@page{size:A4 portrait;margin:8mm}html,body{background:white!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}#driver-scorecard-dialog,#driver-scorecard-dialog *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body>*:not(#driver-scorecard-dialog){display:none!important}.driver-scorecard-dialog{position:absolute!important;top:0!important;left:0!important;right:auto!important;bottom:auto!important;inset:auto!important;margin:0!important;padding:0!important;display:block!important;max-height:none!important;height:auto!important;min-height:0!important;overflow:visible!important;width:100%!important;max-width:none!important;border:0!important;box-shadow:none!important;transform:none!important}#driver-scorecard-content{height:auto!important;max-height:none!important;overflow:visible!important}.driver-scorecard-dialog::backdrop{display:none!important}.scorecard-actions{display:none!important}}</style></head>', $html);
        return new Response($html);
    }
}
