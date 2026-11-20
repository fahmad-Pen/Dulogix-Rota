<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AccountController extends AbstractController
{
    private const COUNTRIES = [
        'United Kingdom' => '+44',
        'Afghanistan' => '+93',
        'Albania' => '+355',
        'Algeria' => '+213',
        'American Samoa' => '+1684',
        'Andorra' => '+376',
        'Angola' => '+244',
        'Anguilla' => '+1264',
        'Antigua and Barbuda' => '+1268',
        'Argentina' => '+54',
        'Armenia' => '+374',
        'Aruba' => '+297',
        'Australia' => '+61',
        'Austria' => '+43',
        'Azerbaijan' => '+994',
        'Bahamas' => '+1242',
        'Bahrain' => '+973',
        'Bangladesh' => '+880',
        'Barbados' => '+1246',
        'Belarus' => '+375',
        'Belgium' => '+32',
        'Belize' => '+501',
        'Benin' => '+229',
        'Bermuda' => '+1441',
        'Bhutan' => '+975',
        'Bolivia' => '+591',
        'Bosnia and Herzegovina' => '+387',
        'Botswana' => '+267',
        'Bouvet Island' => '+47',
        'Brazil' => '+55',
        'British Indian Ocean Territory' => '+246',
        'British Virgin Islands' => '+1284',
        'Brunei' => '+673',
        'Bulgaria' => '+359',
        'Burkina Faso' => '+226',
        'Burundi' => '+257',
        'Cambodia' => '+855',
        'Cameroon' => '+237',
        'Canada' => '+1',
        'Cape Verde' => '+238',
        'Caribbean Netherlands' => '+599',
        'Cayman Islands' => '+1345',
        'Central African Republic' => '+236',
        'Chad' => '+235',
        'Chile' => '+56',
        'China' => '+86',
        'Christmas Island' => '+61',
        'Cocos (Keeling) Islands' => '+61',
        'Colombia' => '+57',
        'Comoros' => '+269',
        'Congo' => '+242',
        'Cook Islands' => '+682',
        'Costa Rica' => '+506',
        'Croatia' => '+385',
        'Cuba' => '+53',
        'Curaçao' => '+599',
        'Cyprus' => '+357',
        'Czechia' => '+420',
        'DR Congo' => '+243',
        'Denmark' => '+45',
        'Djibouti' => '+253',
        'Dominica' => '+1767',
        'Dominican Republic' => '+1',
        'Ecuador' => '+593',
        'Egypt' => '+20',
        'El Salvador' => '+503',
        'Equatorial Guinea' => '+240',
        'Eritrea' => '+291',
        'Estonia' => '+372',
        'Eswatini' => '+268',
        'Ethiopia' => '+251',
        'Falkland Islands' => '+500',
        'Faroe Islands' => '+298',
        'Fiji' => '+679',
        'Finland' => '+358',
        'France' => '+33',
        'French Guiana' => '+594',
        'French Polynesia' => '+689',
        'French Southern and Antarctic Lands' => '+262',
        'Gabon' => '+241',
        'Gambia' => '+220',
        'Georgia' => '+995',
        'Germany' => '+49',
        'Ghana' => '+233',
        'Gibraltar' => '+350',
        'Greece' => '+30',
        'Greenland' => '+299',
        'Grenada' => '+1473',
        'Guadeloupe' => '+590',
        'Guam' => '+1671',
        'Guatemala' => '+502',
        'Guernsey' => '+44',
        'Guinea' => '+224',
        'Guinea-Bissau' => '+245',
        'Guyana' => '+592',
        'Haiti' => '+509',
        'Honduras' => '+504',
        'Hong Kong' => '+852',
        'Hungary' => '+36',
        'Iceland' => '+354',
        'India' => '+91',
        'Indonesia' => '+62',
        'Iran' => '+98',
        'Iraq' => '+964',
        'Ireland' => '+353',
        'Isle of Man' => '+44',
        'Israel' => '+972',
        'Italy' => '+39',
        'Ivory Coast' => '+225',
        'Jamaica' => '+1876',
        'Japan' => '+81',
        'Jersey' => '+44',
        'Jordan' => '+962',
        'Kazakhstan' => '+7',
        'Kenya' => '+254',
        'Kiribati' => '+686',
        'Kosovo' => '+383',
        'Kuwait' => '+965',
        'Kyrgyzstan' => '+996',
        'Laos' => '+856',
        'Latvia' => '+371',
        'Lebanon' => '+961',
        'Lesotho' => '+266',
        'Liberia' => '+231',
        'Libya' => '+218',
        'Liechtenstein' => '+423',
        'Lithuania' => '+370',
        'Luxembourg' => '+352',
        'Macau' => '+853',
        'Madagascar' => '+261',
        'Malawi' => '+265',
        'Malaysia' => '+60',
        'Maldives' => '+960',
        'Mali' => '+223',
        'Malta' => '+356',
        'Marshall Islands' => '+692',
        'Martinique' => '+596',
        'Mauritania' => '+222',
        'Mauritius' => '+230',
        'Mayotte' => '+262',
        'Mexico' => '+52',
        'Micronesia' => '+691',
        'Moldova' => '+373',
        'Monaco' => '+377',
        'Mongolia' => '+976',
        'Montenegro' => '+382',
        'Montserrat' => '+1664',
        'Morocco' => '+212',
        'Mozambique' => '+258',
        'Myanmar' => '+95',
        'Namibia' => '+264',
        'Nauru' => '+674',
        'Nepal' => '+977',
        'Netherlands' => '+31',
        'New Caledonia' => '+687',
        'New Zealand' => '+64',
        'Nicaragua' => '+505',
        'Niger' => '+227',
        'Nigeria' => '+234',
        'Niue' => '+683',
        'Norfolk Island' => '+672',
        'North Korea' => '+850',
        'North Macedonia' => '+389',
        'Northern Mariana Islands' => '+1670',
        'Norway' => '+47',
        'Oman' => '+968',
        'Pakistan' => '+92',
        'Palau' => '+680',
        'Palestine' => '+970',
        'Panama' => '+507',
        'Papua New Guinea' => '+675',
        'Paraguay' => '+595',
        'Peru' => '+51',
        'Philippines' => '+63',
        'Pitcairn Islands' => '+64',
        'Poland' => '+48',
        'Portugal' => '+351',
        'Puerto Rico' => '+1',
        'Qatar' => '+974',
        'Romania' => '+40',
        'Russia' => '+7',
        'Rwanda' => '+250',
        'Réunion' => '+262',
        'Saint Barthélemy' => '+590',
        'Saint Helena, Ascension and Tristan da Cunha' => '+2',
        'Saint Kitts and Nevis' => '+1869',
        'Saint Lucia' => '+1758',
        'Saint Martin' => '+590',
        'Saint Pierre and Miquelon' => '+508',
        'Saint Vincent and the Grenadines' => '+1784',
        'Samoa' => '+685',
        'San Marino' => '+378',
        'Saudi Arabia' => '+966',
        'Senegal' => '+221',
        'Serbia' => '+381',
        'Seychelles' => '+248',
        'Sierra Leone' => '+232',
        'Singapore' => '+65',
        'Sint Maarten' => '+1721',
        'Slovakia' => '+421',
        'Slovenia' => '+386',
        'Solomon Islands' => '+677',
        'Somalia' => '+252',
        'South Africa' => '+27',
        'South Georgia' => '+500',
        'South Korea' => '+82',
        'South Sudan' => '+211',
        'Spain' => '+34',
        'Sri Lanka' => '+94',
        'Sudan' => '+249',
        'Suriname' => '+597',
        'Svalbard and Jan Mayen' => '+4779',
        'Sweden' => '+46',
        'Switzerland' => '+41',
        'Syria' => '+963',
        'São Tomé and Príncipe' => '+239',
        'Taiwan' => '+886',
        'Tajikistan' => '+992',
        'Tanzania' => '+255',
        'Thailand' => '+66',
        'Timor-Leste' => '+670',
        'Togo' => '+228',
        'Tokelau' => '+690',
        'Tonga' => '+676',
        'Trinidad and Tobago' => '+1868',
        'Tunisia' => '+216',
        'Turkmenistan' => '+993',
        'Turks and Caicos Islands' => '+1649',
        'Tuvalu' => '+688',
        'Türkiye' => '+90',
        'Uganda' => '+256',
        'Ukraine' => '+380',
        'United Arab Emirates' => '+971',
        'United States' => '+1',
        'United States Minor Outlying Islands' => '+268',
        'United States Virgin Islands' => '+1340',
        'Uruguay' => '+598',
        'Uzbekistan' => '+998',
        'Vanuatu' => '+678',
        'Vatican City' => '+3',
        'Venezuela' => '+58',
        'Vietnam' => '+84',
        'Wallis and Futuna' => '+681',
        'Western Sahara' => '+2',
        'Yemen' => '+967',
        'Zambia' => '+260',
        'Zimbabwe' => '+263',
        'Åland Islands' => '+35818',
    ];

    private function phoneOptions(string $selected = 'United Kingdom'): string
    {
        $html = '';
        foreach (self::COUNTRIES as $country => $code) {
            $html .= '<option value="'.htmlspecialchars($country).'"'.($country === $selected ? ' selected' : '').'>'.htmlspecialchars($country.' ('.$code.')').'</option>';
        }
        return $html;
    }

    private function phoneParts(?string $phone): array
    {
        if (!$phone) return ['United Kingdom', ''];
        $matches = [];
        foreach (self::COUNTRIES as $country => $code) {
            if (str_starts_with($phone, $code)) $matches[$country] = $code;
        }
        if ($matches) {
            uasort($matches, static fn($a, $b) => strlen($b) <=> strlen($a));
            $country = array_key_first($matches);
            return [$country, substr($phone, strlen($matches[$country]))];
        }
        return ['United Kingdom', $phone];
    }

    private function phoneValue(Request $request): ?string
    {
        $country = (string) $request->request->get('country_code', 'United Kingdom');
        $local = trim((string) $request->request->get('phone'));
        if ($local === '') return '';
        if (!isset(self::COUNTRIES[$country]) || str_starts_with($local, '+')) return null;
        $digits = preg_replace('/[\s().-]+/', '', $local);
        if (!ctype_digit($digits)) return null;
        $value = self::COUNTRIES[$country].ltrim($digits, '0');
        return preg_match('/^\+[1-9][0-9]{7,14}$/', $value) ? $value : null;
    }

    private function page(string $title, string $body): Response
    {
        return new Response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · '.htmlspecialchars($title).'</title><link rel="icon" type="image/png" href="/favicon.png"><link rel="stylesheet" href="/users-page.css?v=2"></head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a> '.SectionAccess::navigation($this->getUser()).'</header><main><h1>'.htmlspecialchars($title).'</h1>'.$body.'</main><script defer src="/users-page.js?v=1"></script></body></html>');
    }

    #[Route('/admin/users', name: 'admin_users', methods: ['GET', 'POST'])]
    public function users(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, CsrfTokenManagerInterface $csrfManager): Response
    {
        $driversView = $request->query->get('view') === 'drivers' || !$this->isGranted('ROLE_ADMIN');
        if ($driversView) {
            if (!(SectionAccess::allowed($this->getUser(), 'rota') || SectionAccess::performanceLevel($this->getUser()) === 'full')) throw $this->createAccessDeniedException();
        } else {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }
        $message = '';
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_user', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token');
            }
            $username = mb_strtolower(trim((string) $request->request->get('username')));
            $name = trim((string) $request->request->get('name'));
            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $phone = $this->phoneValue($request);
            $transporterId = strtoupper(trim((string) $request->request->get('transporter_id', '')));
            $role = (string) $request->request->get('role');
            $type = $role === 'ROLE_DRIVER' ? (string)$request->request->get('driver_type', 'DRIVER') : 'NONE';
            $password = (string) $request->request->get('password');
            $errors = [];
            if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username)) $errors[] = 'Username must be 3–80 characters and contain only lowercase letters, numbers, dots, underscores or hyphens.';
            if ($name === '') $errors[] = 'Full name is required.';
            if ($transporterId !== '' && (!preg_match('/^[A-Z0-9]{1,40}$/', $transporterId) || $role !== 'ROLE_DRIVER')) $errors[] = 'Transporter ID must contain 1–40 letters or numbers and belong to a driver.';
            if ($transporterId !== '' && $em->getRepository(User::class)->findOneBy(['transporterId' => $transporterId])) $errors[] = 'This Transporter ID is already assigned to another account.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if ($phone === null) $errors[] = 'Enter a valid phone number for the selected country.';
            if (!in_array($role, $driversView ? ['ROLE_DRIVER'] : ['ROLE_DRIVER','ROLE_MANAGER','ROLE_ADMIN'], true)) $errors[] = 'Select a valid account type.';
            if (!in_array($type, $role === 'ROLE_DRIVER' ? ['DRIVER','JUMPER'] : ['NONE'], true)) $errors[] = 'Operational type does not match the account type.';
            if ($role === 'ROLE_DRIVER' ? !DriverPasswordPolicy::valid($password) : strlen($password) < 12) $errors[] = $role === 'ROLE_DRIVER' ? DriverPasswordPolicy::MESSAGE : 'Temporary password must contain at least 12 characters.';
            if ($em->getRepository(User::class)->findOneBy(['username' => $username])) $errors[] = 'This username is already in use.';
            if ($errors) {
                $message = '<div class="panel error" role="alert"><strong>Account could not be created:</strong><ul><li>'.implode('</li><li>',array_map(static fn($error)=>htmlspecialchars($error,ENT_QUOTES),$errors)).'</li></ul>Your details are preserved below; please correct the highlighted issues.</div>';
            } else {
                $user = (new User())->setUsername($username)->setDisplayName($name)->setEmail($email)->setPhone($phone ?: null)->setRoles([$role])->setDriverType($type)->setTransporterId($transporterId ?: null);
                $user->setSections($this->isGranted('ROLE_ADMIN') && $role === 'ROLE_MANAGER' ? SectionAccess::submittedSections($request) : []);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();
                $message = '<p class="ok">Account created.</p>';
            }
        }
        $rows = '';
        $rowNumber = 0;
        foreach ($em->getRepository(User::class)->findBy([], ['id' => 'ASC']) as $user) {
            if ($driversView && !in_array('ROLE_DRIVER', $user->getRoles(), true)) continue;
            ++$rowNumber;
            $rows .= '<tr data-active="'.($user->isActive() ? 'yes' : 'no').'"><td>'.$rowNumber.'</td><td>'.htmlspecialchars($user->getUsername()).'</td><td>'.htmlspecialchars($user->getDisplayName()).($user->getTransporterId() ? '<br><small class="user-css-0c43289c19">Transporter ID: '.htmlspecialchars($user->getTransporterId()).'</small>' : '').'</td><td>'.htmlspecialchars($user->getEmail()).'</td><td>'.htmlspecialchars($user->getPhone() ?? '').'</td><td>'.htmlspecialchars(implode(', ', $user->getRoles())).'</td><td>'.htmlspecialchars($user->getDriverType()).'</td><td><span class="user-status-pill '.($user->isActive() ? 'user-status-active' : 'user-status-inactive').'">'.($user->isActive() ? '● Active' : '● Inactive').'</span></td><td><div class="user-css-940d531893"><a href="/admin/users/edit/'.$user->getId().'" class="user-css-93d4dc5b86">Edit</a> '.($this->isGranted('ROLE_ADMIN') && $user->getId() !== $this->getUser()?->getId() ? '<form method="post" class="user-css-8372edcbb8" action="/admin/users/delete/'.$user->getId().'"><input type="hidden" name="_token" value="'.htmlspecialchars($csrfManager->getToken('delete_user_'.$user->getId())->getValue()).'"><button type="submit" class="user-css-cfb5f3f6e1" data-confirm-delete="1">Delete</button></form>' : '').'</div></td></tr>';
        }
        $posted = $request->isMethod('POST') && !empty($errors);
        $old = static fn(string $field) => $posted ? htmlspecialchars((string)$request->request->get($field, ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        $oldRole = $posted ? (string)$request->request->get('role') : 'ROLE_DRIVER';
        $oldType = $posted ? (string)$request->request->get('driver_type') : 'DRIVER';
        $oldSections = $posted ? $request->request->all('sections') : [];
        $country = $posted ? (string)$request->request->get('country_code', '+44') : '+44';
        $token = $csrfManager->getToken('create_user')->getValue();
        $importPanel = '';
        if (!$driversView && $this->isGranted('ROLE_ADMIN')) {
            $importToken = htmlspecialchars($csrfManager->getToken('import_users_excel')->getValue(),ENT_QUOTES);
            $importPanel = '<div class="panel"><h2>Excel user import / export (administrators only)</h2><p>Download the existing-user template, complete every field for new users, and upload the XLSX file. Existing usernames are skipped without changing passwords.</p><p><a href="/admin/users/excel/export" class="user-css-9cdd027d18">Export Excel Template</a></p><form action="/admin/users/excel/import" method="post" enctype="multipart/form-data"><input type="hidden" name="_token" value="'.$importToken.'"><label for="users-excel-file">Import completed Excel file (.xlsx)</label><input id="users-excel-file" type="file" name="excel_file" accept=".xlsx" required><button type="submit">Validate and Import Users</button></form><p><small>The template has ten columns; Transporter ID is optional for drivers and must be blank for managers and administrators. All other columns are required for new accounts. For non-driver accounts use N/A for Operational Type; for non-managers use N/A for Manager Permissions. Managers use rota, vehicle and/or operations. Password = lowercase username + Password Suffix; Driver/Jumper passwords need 8+ characters, a number and a special character; Manager/Admin passwords need 12+ characters. Do not share completed templates containing password suffixes.</small></p></div>';
        }
        foreach ($request->getSession()->getFlashBag()->get('user_import',[]) as $notice) $message .= '<p class="ok">'.htmlspecialchars($notice,ENT_QUOTES).'</p>';
        $roleFilter = $driversView ? '' : '<div class="user-filter-field"><label for="user-role-filter">Filter by role</label><select id="user-role-filter" class="user-css-58a8a2ed8d"><option value="all">All roles</option><option value="ROLE_ADMIN">Administrator</option><option value="ROLE_MANAGER">Manager</option><option value="ROLE_DRIVER">Driver / Jumper</option></select></div>';
        $body = $message.$importPanel.'<div class="panel driver-create-panel"><div class="driver-panel-heading"><div class="driver-heading-icon">+</div><div><h2>Create driver account</h2><p>Add a driver or jumper to your team</p></div></div><form method="post" class="user-create-form"><input type="hidden" name="_token" value="'.htmlspecialchars($token).'"><label>Username</label><input name="username" autocomplete="username" value="'.$old('username').'" required minlength="3"><label>Full name</label><input name="name" autocomplete="name" value="'.$old('name').'" required><label>Email</label><input type="email" name="email" autocomplete="email" value="'.$old('email').'" required><label for="user-phone-number">Phone</label><div class="phone-row"><select name="country_code" aria-label="Country code">'.$this->phoneOptions($country).'</select><input id="user-phone-number" name="phone" aria-label="Phone number" autocomplete="tel-national" value="'.$old('phone').'" type="tel" inputmode="tel" placeholder="7700 900123"></div><label>Account type</label><select name="role"><option value="ROLE_DRIVER"'.($oldRole==='ROLE_DRIVER'?' selected':'').'>Driver</option>'.(!$driversView && $this->isGranted('ROLE_ADMIN') ? '<option value="ROLE_MANAGER"'.($oldRole==='ROLE_MANAGER'?' selected':'').'>Manager</option><option value="ROLE_ADMIN"'.($oldRole==='ROLE_ADMIN'?' selected':'').'>Administrator</option>' : '').'</select><div class="operational-type-field"><label>Operational type</label><select name="driver_type"><option value="DRIVER"'.($oldType==='DRIVER'?' selected':'').'>Driver</option><option value="JUMPER"'.($oldType==='JUMPER'?' selected':'').'>Jumper</option></select></div><div class="transporter-id-field"><label>Transporter ID (optional)</label><input name="transporter_id" maxlength="40" pattern="[A-Za-z0-9]*" value="'.$old('transporter_id').'" placeholder="A1VHM869VR7YEE"></div>'.(!$driversView && $this->isGranted('ROLE_ADMIN') ? '<fieldset id="manager-sections" class="user-css-76b35b91b4"><legend>Manager sections</legend>'.SectionAccess::accessSelect('rota', $oldSections).SectionAccess::accessSelect('vehicle', $oldSections).SectionAccess::accessSelect('operations', $oldSections).'<label class="user-css-8eaa6732b6">Driver Performance <select name="performance_access" class="user-css-30e741d98e"><option value="none">No access</option><option value="performance_read">Read Only</option><option value="performance_full">Full Access</option></select></label><small>Assign one or more application sections to this manager.</small></fieldset>' : '').'<label>Temporary password (Driver/Jumper: 8+ with number and special character; others: 12+)</label><input name="password" aria-label="Temporary password" type="password" minlength="8" required autocomplete="new-password"><button>Create account</button></form></div><div class="panel driver-users-panel"><div class="driver-panel-heading"><div class="driver-heading-icon driver-people-icon">&#9776;</div><div><h2>Driver directory</h2><p>Find and manage existing accounts</p></div><span class="driver-user-count" id="driver-user-count"></span></div><div class="user-filter-bar"><div class="user-filter-field"><label for="user-search">Search drivers</label><input id="user-search" type="search" placeholder="Search users…" ></div>'.$roleFilter.($driversView ? '' : '').'<div class="user-filter-field"><label for="user-active-filter">Filter by status</label><select id="user-active-filter" class="user-css-58a8a2ed8d"><option value="all">All statuses</option><option value="yes">Active</option><option value="no">Inactive</option></select></div><div class="user-filter-actions"><button type="button" id="user-filter-clear">Clear filters</button></div></div><div class="user-css-a632715a5f"><table id="users-table" data-drivers-view="'.($driversView ? '1' : '0').'"><thead><tr><th>#</th><th>Username</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody>'.$rows.'</tbody></table></div></div>';
        return $this->page($driversView ? 'Drivers' : 'User management', $body);
    }


    #[Route('/admin/users/edit/{id}', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editUser(int $id, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfManager): Response
    {
        if (!(SectionAccess::allowed($this->getUser(), 'rota') || SectionAccess::performanceLevel($this->getUser()) === 'full')) throw $this->createAccessDeniedException();
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) throw $this->createNotFoundException();
        $admin = $this->isGranted('ROLE_ADMIN');
        if (!$admin && !in_array('ROLE_DRIVER', $user->getRoles(), true)) throw $this->createAccessDeniedException();
        $message = '';
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_user_'.$id, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $name = trim((string) $request->request->get('name'));
            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $phone = $this->phoneValue($request);
            $transporterId = strtoupper(trim((string) $request->request->get('transporter_id', $user->getTransporterId() ?? '')));
            $role = in_array('ROLE_ADMIN', $user->getRoles(), true) ? 'ROLE_ADMIN' : (in_array('ROLE_MANAGER', $user->getRoles(), true) ? 'ROLE_MANAGER' : 'ROLE_DRIVER');
            if ($request->request->has('role') && (string)$request->request->get('role') !== $role) throw $this->createAccessDeniedException('Account role cannot be changed after creation.');
            $type = $role === 'ROLE_DRIVER' ? (string)$request->request->get('driver_type') : 'NONE';
            $allowedRoles = $admin ? ['ROLE_DRIVER', 'ROLE_MANAGER', 'ROLE_ADMIN'] : ['ROLE_DRIVER'];
            $existingTransporter = $transporterId !== '' ? $em->getRepository(User::class)->findOneBy(['transporterId' => $transporterId]) : null;
            if ($name === '' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $phone === null || !in_array($type,$role === 'ROLE_DRIVER' ? ['DRIVER','JUMPER'] : ['NONE'],true) || !in_array($role,$allowedRoles,true) || ($transporterId !== '' && (!preg_match('/^[A-Z0-9]{1,40}$/', $transporterId) || $role !== 'ROLE_DRIVER')) || ($existingTransporter && $existingTransporter->getId() !== $user->getId())) {
                $message='<p class="error">Check the account details and permissions.</p>';
            } else {
                $user->setDisplayName($name)->setEmail($email)->setPhone($phone ?: null)->setDriverType($type);
                if ($role === 'ROLE_DRIVER') $user->setTransporterId($transporterId ?: null);
                if ($admin) $user->setSections($role === 'ROLE_MANAGER' ? SectionAccess::submittedSections($request) : []);
                $em->flush();
                $message='<p class="ok">Account updated.</p>';
            }
        }
        $e=static fn(?string $v)=>htmlspecialchars($v??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        [$country,$local]=$this->phoneParts($user->getPhone());
        $options='';
        foreach (['DRIVER'=>'Driver','JUMPER'=>'Jumper'] as $value=>$label) {
            if ($value==='MANAGER' && !$admin) continue;
            $options.='<option value="'.$value.'"'.($user->getDriverType()===$value?' selected':'').'>'.$label.'</option>';
        }
        $roles='';
        foreach (['ROLE_DRIVER'=>'Driver','ROLE_MANAGER'=>'Manager','ROLE_ADMIN'=>'Administrator'] as $value=>$label) {
            if (!$admin && $value!=='ROLE_DRIVER') continue;
            $roles.='<option value="'.$value.'"'.(in_array($value,$user->getRoles(),true)?' selected':'').'>'.$label.'</option>';
        }
        $roleLabel = in_array('ROLE_ADMIN',$user->getRoles(),true) ? 'Administrator' : (in_array('ROLE_MANAGER',$user->getRoles(),true) ? 'Manager' : 'Driver');
        $driverControls = '';
        if (in_array('ROLE_DRIVER', $user->getRoles(), true) && !in_array('ROLE_MANAGER', $user->getRoles(), true) && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $statusToken = $e($csrfManager->getToken('status_driver_'.$id)->getValue());
            $resetToken = $e($csrfManager->getToken('reset_driver_'.$id)->getValue());
            $driverControls = '<div class="panel"><h2>Driver access</h2><p>Status: <strong>'.($user->isActive() ? 'Active' : 'Inactive').'</strong></p><form method="post" action="/admin/users/status/'.$id.'"><input type="hidden" name="_token" value="'.$statusToken.'"><button type="submit">'.($user->isActive() ? 'Deactivate driver' : 'Reactivate driver').'</button></form><h2>Reset driver password</h2><form method="post" action="/admin/users/reset-password/'.$id.'"><input type="hidden" name="_token" value="'.$resetToken.'"><input type="text" class="password-username-assist" name="username" autocomplete="username" tabindex="-1" aria-label="Username" readonly value="'.htmlspecialchars($user->getUsername(), ENT_QUOTES).'"><label for="reset-temporary-password">New temporary password (minimum 8 characters, including a number and special character)</label><input id="reset-temporary-password" type="password" name="temporary_password" minlength="8" required autocomplete="new-password"><button type="submit">Set temporary password</button></form></div>';
        }
        if ($admin && $roleLabel !== 'Driver') {
            $resetToken = $e($csrfManager->getToken('reset_driver_'.$id)->getValue());
            $driverControls .= '<div class="panel"><h2>Reset account password</h2><p>Minimum 12 characters.</p><form method="post" action="/admin/users/reset-password/'.$id.'"><input type="hidden" name="_token" value="'.$resetToken.'"><input type="text" class="password-username-assist" name="username" autocomplete="username" tabindex="-1" aria-label="Username" readonly value="'.htmlspecialchars($user->getUsername(), ENT_QUOTES).'"><label for="reset-temporary-password">New temporary password</label><input id="reset-temporary-password" type="password" name="temporary_password" minlength="12" required autocomplete="new-password"><button type="submit">Set temporary password</button></form></div>';
        }
        $managerSections = '';
        if ($admin) {
            $checked = in_array('rota', $user->getSections(), true) ? ' checked' : '';
            $vehicleChecked = in_array('vehicle', $user->getSections(), true) ? ' checked' : '';
            $operationsChecked = in_array('operations', $user->getSections(), true) ? ' checked' : '';
            $performanceSelected = in_array('performance_full', $user->getSections(), true) ? 'performance_full' : (in_array('performance_read', $user->getSections(), true) ? 'performance_read' : 'none');
            $managerSections = '<fieldset id="manager-sections" class="user-css-6e85adaf2d"><legend>Manager sections</legend>'.SectionAccess::accessSelect('rota', $user->getSections()).SectionAccess::accessSelect('vehicle', $user->getSections()).SectionAccess::accessSelect('operations', $user->getSections()).'<label class="user-css-7a63e04cd7">Driver Performance access<select name="performance_access"><option value="none"'.($performanceSelected==='none'?' selected':'').'>No access</option><option value="performance_read"'.($performanceSelected==='performance_read'?' selected':'').'>Read Only</option><option value="performance_full"'.($performanceSelected==='performance_full'?' selected':'').'>Full Access</option></select></label><small>Assign the application sections this manager can access.</small></fieldset>';
        }
        return $this->page('Edit user', $message.$driverControls.($roleLabel==='Driver'?'<div class="panel"><h2>Training &amp; requirements</h2><p>Track WISE, Cognition, Ementor, Clear Quote, Badge and any new requirements. Each completion records who checked it and when.</p><div id="driver-training-checklist" data-url="/admin/users/'.$id.'/requirements"><p>Loading requirements…</p></div></div>':'').'<div class="panel"><form method="post"><input type="hidden" name="_token" value="'.$e($csrfManager->getToken('edit_user_'.$id)->getValue()).'"><p>Username: <b>'.$e($user->getUsername()).'</b></p><label for="edit-user-name">Full name</label><input id="edit-user-name" name="name" autocomplete="name" value="'.$e($user->getDisplayName()).'" required>'.($roleLabel==='Driver'?'<label for="edit-transporter-id">Transporter ID (optional)</label><input id="edit-transporter-id" name="transporter_id" autocomplete="off" maxlength="40" pattern="[A-Za-z0-9]+" value="'.$e($user->getTransporterId()).'" placeholder="A1VHM869VR7YEE">':'').'<label for="edit-user-email">Email</label><input id="edit-user-email" name="email" type="email" autocomplete="email" value="'.$e($user->getEmail()).'" required><label for="user-phone-number">Phone</label><div class="phone-row"><select name="country_code" aria-label="Phone country code" autocomplete="tel-country-code">'.$this->phoneOptions($country).'</select><input id="user-phone-number" name="phone" type="tel" autocomplete="tel-national" value="'.$e($local).'"></div><p><strong>Account role:</strong> '.$e($roleLabel).' 🔒</p><p class="user-css-f756cc3308">Account role is permanent. Create a new account for a different role; deactivate the old Driver account to preserve history.</p><div class="operational-type-field"><label for="edit-driver-type">Operational type</label><select id="edit-driver-type" name="driver_type">'.$options.'</select></div>'.$managerSections.'<br><button>Save account</button> <a href="/admin/users">Cancel</a></form></div>');
    }

    #[Route('/admin/users/status/{id}', name: 'admin_user_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changeDriverStatus(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        $user = $em->getRepository(User::class)->find($id);
        if (!$user || !in_array('ROLE_DRIVER', $user->getRoles(), true) || in_array('ROLE_MANAGER', $user->getRoles(), true) || in_array('ROLE_ADMIN', $user->getRoles(), true)) throw $this->createAccessDeniedException();
        if (!$this->isCsrfTokenValid('status_driver_'.$id, (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
        $user->setActive(!$user->isActive());
        $em->flush();
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/reset-password/{id}', name: 'admin_user_reset_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resetDriverPassword(int $id, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
        $user = $em->getRepository(User::class)->find($id);
        if (!$user || (!$this->isGranted('ROLE_ADMIN') && (!in_array('ROLE_DRIVER', $user->getRoles(), true) || in_array('ROLE_MANAGER', $user->getRoles(), true) || in_array('ROLE_ADMIN', $user->getRoles(), true)))) throw $this->createAccessDeniedException();
        if (!$this->isCsrfTokenValid('reset_driver_'.$id, (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
        $password = (string)$request->request->get('temporary_password');
        $isDriver = in_array('ROLE_DRIVER', $user->getRoles(), true) && !in_array('ROLE_MANAGER', $user->getRoles(), true) && !in_array('ROLE_ADMIN', $user->getRoles(), true);
        if ($isDriver ? !DriverPasswordPolicy::valid($password) : strlen($password) < 12) return new Response($isDriver ? DriverPasswordPolicy::MESSAGE : 'Password must be at least 12 characters.', 422);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em->flush();
        return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
    }

    #[Route('/admin/users/delete/{id}', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteUser(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('delete_user_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) throw $this->createNotFoundException();
        if ($user->getId() === $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('You cannot delete your own account.');
        }
        // Preserve historical and operational records. Several legacy tables store
        // driver IDs without foreign keys, so ORM cascade checks alone are insufficient.
        $connection = $em->getConnection();
        $schema = $connection->createSchemaManager();
        foreach ($schema->listTableNames() as $table) {
            if ($table === 'app_user') {
                continue;
            }
            foreach ($schema->listTableColumns($table) as $column) {
                $field = strtolower($column->getName());
                if (!preg_match('/(?:^|_)(?:driver|user|actor|manager|completed_by|from_driver|to_driver)_id$/', $field)) {
                    continue;
                }
                $sql = 'SELECT 1 FROM '.$connection->quoteIdentifier($table)
                    .' WHERE '.$connection->quoteIdentifier($column->getName()).' = ? LIMIT 1';
                if ($connection->fetchOne($sql, [$id]) !== false) {
                    return new Response(
                        'This account has linked operational or audit records and cannot be deleted safely. '
                        .'Deactivate the account from Edit instead to preserve history.',
                        Response::HTTP_CONFLICT
                    );
                }
            }
        }
        $em->remove($user);
        try {
            $em->flush();
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $exception) {
            return new Response(
                'This account is referenced by existing records and cannot be deleted. Deactivate it instead.',
                Response::HTTP_CONFLICT
            );
        }
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/account', name: 'account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, CsrfTokenManagerInterface $csrfManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();
        $message = '';
        $driverOnly = in_array('ROLE_DRIVER', $user->getRoles(), true) && !$this->isGranted('ROLE_OPERATIONS');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_profile', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $email = $driverOnly ? $user->getEmail() : mb_strtolower(trim((string) $request->request->get('email')));
            $phone = $driverOnly ? $user->getPhone() : $this->phoneValue($request);
            $name = $driverOnly ? $user->getDisplayName() : trim((string) $request->request->get('name'));
            $newPassword = (string) $request->request->get('new_password');
            $currentPassword = (string) $request->request->get('current_password');
            if (($driverOnly && $newPassword === '') || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === null || $name === '' || mb_strlen($name) > 120 || mb_strlen($phone) > 40 || ($newPassword !== '' && ($driverOnly ? !DriverPasswordPolicy::valid($newPassword) : strlen($newPassword) < 12)) || ($newPassword !== '' && !$hasher->isPasswordValid($user, $currentPassword)) || (($existing = $em->getRepository(User::class)->findOneBy(['email' => $email])) && $existing->getId() !== $user->getId())) {
                $message = '<p class="error">Check your details. Driver/Jumper passwords need 8+ characters, a number and a special character; other accounts need 12+ characters. Enter your current password.</p>';
            } else {
                if (!$driverOnly) $user->setEmail($email)->setPhone($phone ?: null)->setDisplayName($name);
                if ($newPassword !== '') $user->setPassword($hasher->hashPassword($user, $newPassword));
                $em->flush();
                $message = '<p class="ok">'.($driverOnly ? 'Password updated (if a new password was entered).' : 'Details saved.').'</p>';
            }
        }
        $e = static fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        [$countryCode, $localPhone] = $this->phoneParts($user->getPhone());
        $token = $csrfManager->getToken('edit_profile')->getValue();
        return $this->page('My account', $message.'<div class="panel"><form method="post"><input type="hidden" name="_token" value="'.$e($token).'"><input type="text" class="password-username-assist" name="username" autocomplete="username" aria-label="Username" tabindex="-1" readonly value="'.$e($user->getUsername()).'"><p>Username: <b>'.$e($user->getUsername()).'</b></p>'.($driverOnly ? '<p><strong>Full name:</strong> '.$e($user->getDisplayName()).'</p><p><strong>Email:</strong> '.$e($user->getEmail()).'</p><p><strong>Phone:</strong> '.$e($user->getPhone() ?: 'Not provided').'</p><p>To update these details, contact your manager or administrator.</p>' : '<label for="account-name">Full name</label><input id="account-name" name="name" autocomplete="name" required value="'.$e($user->getDisplayName()).'">').($driverOnly ? '<h2>Change password</h2>' : '<label for="account-email">Email</label><input id="account-email" name="email" type="email" autocomplete="email" required value="'.$e($user->getEmail()).'"><label for="user-phone-number">Phone</label><div class="phone-row"><select name="country_code" aria-label="Country code" autocomplete="tel-country-code">'.$this->phoneOptions($countryCode).'</select><input id="user-phone-number" name="phone" type="tel" autocomplete="tel-national" inputmode="tel" placeholder="7700 900123" value="'.$e($localPhone).'"></div><h2>Change password (optional)</h2>').'<label for="account-current-password">Current password</label><input id="account-current-password" name="current_password" type="password" autocomplete="current-password"><label for="account-new-password">New password</label><p>'.($driverOnly ? 'Minimum 8 characters, including at least 1 number and 1 special character (e.g. @, #, !).' : 'Minimum 12 characters.').'</p><input id="account-new-password" name="new_password" type="password" autocomplete="new-password" minlength="'.($driverOnly ? '8' : '12').'"><br><button>Save changes</button></form></div>');
    }
}