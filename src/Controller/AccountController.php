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
        return new Response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · '.htmlspecialchars($title).'</title><link rel="icon" type="image/png" href="/favicon.png"><style>*{box-sizing:border-box}body{font:13px system-ui;background:#f5f7fb;color:#24364b;margin:0}header{background:#152d49;color:white;padding:12px 20px}header img{height:32px;width:auto;vertical-align:middle;margin-right:16px;background:white;border-radius:4px;padding:2px}header a{color:#d6e8fc;margin-right:18px}main{max-width:1080px;margin:16px auto;padding:0 12px}.panel{background:white;border:1px solid #e0e7ef;border-radius:7px;padding:14px;margin-bottom:12px}h1{font-size:18px;margin:0 0 12px}h2{font-size:14px}label{display:block;font-weight:600;margin:8px 0 4px}input,select{padding:7px;border:1px solid #cbd7e4;border-radius:4px;width:100%;max-width:420px}.phone-row{display:flex;gap:8px;width:100%;max-width:420px}.phone-row select{width:195px;flex:0 0 195px;min-width:0}.phone-row input{width:0;min-width:0;flex:1 1 auto}button{padding:8px 12px;border:0;border-radius:4px;background:#1a4c77;color:white;cursor:pointer;margin-top:12px}table{width:100%;border-collapse:collapse}th,td{padding:7px;text-align:left;border-bottom:1px solid #e5eaf1}tr:nth-child(even){background:#f1f5fa}.error{color:#b22e36}.ok{color:#13774b}#users-table td:nth-child(8){white-space:nowrap}#users-table td:nth-child(8) span{display:inline-flex!important;align-items:center;gap:5px;white-space:nowrap;min-width:94px;justify-content:center}.mobile-name-sort{display:none}@media(max-width:767px){.mobile-name-sort{display:inline-block;background:#e4effb;color:#173c65;font-weight:700;margin:10px 0;padding:10px 14px}}#users-table .name-sort{border:0;background:transparent;color:#173c65;font-weight:700;cursor:pointer;padding:5px 3px;margin:0;white-space:nowrap}#users-table .user-accordion-toggle{display:none}#users-table .user-desktop-number{display:inline}@media(max-width:767px){main{padding:0 10px;margin:12px auto}.panel{padding:12px;border-radius:10px}input,select{max-width:100%;min-height:44px;font-size:16px}.phone-row{max-width:100%}.phone-row select{width:42%;flex:0 0 42%}button{min-height:44px}#user-search{width:100%}#users-table{display:block;width:100%;border:0}#users-table thead{display:none}#users-table tbody{display:block}#users-table tbody tr{display:grid;grid-template-columns:1fr 1fr;gap:0;margin:12px 0;border:1px solid #d9e4f0;border-radius:10px;overflow:hidden;background:#fff!important;box-shadow:0 2px 6px #173c650d}#users-table tbody tr[hidden]{display:none!important}#users-table td{display:block;min-width:0;overflow-wrap:anywhere;padding:9px 11px;border-bottom:1px solid #edf1f6;font-size:12px;background:#fff!important}#users-table td::before{content:attr(data-label);display:block;font-size:10px;font-weight:700;color:#60748b;margin-bottom:4px}#users-table td:nth-child(1){grid-column:1/2;background:#eaf2fc!important;font-weight:700}#users-table td:nth-child(2){grid-column:2/3;background:#eaf2fc!important;font-weight:700}#users-table td:nth-child(3),#users-table td:nth-child(4),#users-table td:nth-child(5),#users-table td:nth-child(8),#users-table td:nth-child(9){grid-column:1/-1}#users-table td:nth-child(3){font-weight:700;font-size:15px}#users-table td:nth-child(9){display:flex;flex-wrap:wrap;gap:10px;align-items:center}#users-table td:nth-child(9)::before{flex-basis:100%}#users-table td:nth-child(9) form{display:inline-flex;margin:0}#users-table td:nth-child(9) button{margin:0}#users-table td:nth-child(9) a{min-height:44px;display:inline-flex!important;align-items:center}#users-table td:nth-child(4){word-break:break-word}#users-table tbody tr{position:relative}#users-table tbody tr td:first-child{padding:0!important}#users-table .user-desktop-number{display:none}#users-table .user-accordion-toggle{display:flex;align-items:center;gap:9px;width:100%;padding:13px 11px;min-height:48px;border:0;border-radius:0;background:#eaf2fc;color:#173c65;text-align:left;font-size:13px;font-weight:700;margin:0;cursor:pointer}#users-table .user-accordion-toggle .user-chevron{font-size:16px}#users-table tbody tr:not(.user-expanded) td:nth-child(n+3){display:none!important}#users-table tbody tr:not(.user-expanded) td:nth-child(2){border-bottom:0}#users-table tbody tr.user-expanded{box-shadow:0 4px 14px #173c6520}#users-table tbody tr.user-expanded td:nth-child(1),#users-table tbody tr.user-expanded td:nth-child(2){border-bottom:2px solid #c6d9ef}}
</style>'.SectionAccess::CSS.'</head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a> '.SectionAccess::navigation($this->getUser()).'</header><main><h1>'.htmlspecialchars($title).'</h1>'.$body.'</main><script>document.querySelectorAll("#users-table tbody tr").forEach(row=>Array.from(row.cells).forEach((cell,i)=>cell.dataset.label=["#","Username","Name","Email","Phone","Role","Type","Status","Actions"][i]));document.querySelectorAll("#users-table tbody tr").forEach(row=>{const first=row.cells[0];if(!first)return;const button=document.createElement("button");button.type="button";button.className="user-accordion-toggle";button.setAttribute("aria-expanded","false");button.setAttribute("aria-label","Show account details for "+row.cells[2].textContent);const chevron=document.createElement("span");chevron.className="user-chevron";chevron.textContent="▸";const number=document.createElement("span");number.textContent=first.textContent;button.append(chevron,number);first.textContent="";const desktopNumber=document.createElement("span");desktopNumber.className="user-desktop-number";desktopNumber.textContent=number.textContent;first.append(desktopNumber,button);button.addEventListener("click",()=>{if(!window.matchMedia("(max-width:767px)").matches)return;const opening=!row.classList.contains("user-expanded");document.querySelectorAll("#users-table tbody tr.user-expanded").forEach(other=>{other.classList.remove("user-expanded");other.querySelector(".user-accordion-toggle").setAttribute("aria-expanded","false");other.querySelector(".user-chevron").textContent="▸";});if(opening){row.classList.add("user-expanded");button.setAttribute("aria-expanded","true");chevron.textContent="▾";requestAnimationFrame(()=>requestAnimationFrame(()=>{const top=row.getBoundingClientRect().top+window.scrollY-12;window.scrollTo({top:Math.max(0,top),behavior:"smooth"});}));}});});const usersTable=document.getElementById("users-table");const roleFilter=document.getElementById("user-role-filter");const activeFilter=document.getElementById("user-active-filter");const driverView=usersTable?.dataset.driversView==="1";const userSearch=document.getElementById("user-search");function filterUsers(){if(!usersTable)return;const query=(userSearch?.value||"").toLowerCase().trim();const role=roleFilter?.value||"all";Array.from(usersTable.tBodies[0].rows).forEach(row=>{const matchName=row.cells[2].textContent.toLowerCase().includes(query)||row.cells[1].textContent.toLowerCase().includes(query);const matchRole=role==="all"||(!driverView&&row.cells[5].textContent.includes(role));const matchActive=(activeFilter?.value||"all")==="all"||row.dataset.active===activeFilter.value;row.hidden=!(matchName&&matchRole&&matchActive)});}userSearch?.addEventListener("input",filterUsers);roleFilter?.addEventListener("change",filterUsers);activeFilter?.addEventListener("change",filterUsers);if(usersTable){const nameHeader=usersTable.querySelector("thead th:nth-child(3)");const sortButton=document.createElement("button");sortButton.type="button";sortButton.className="name-sort";sortButton.textContent="Name A–Z ↑";nameHeader.replaceChildren(sortButton);let ascending=true;function sortNames(){const tbody=usersTable.tBodies[0];const rows=Array.from(tbody.rows);rows.sort((a,b)=>(ascending?1:-1)*a.cells[2].textContent.trim().localeCompare(b.cells[2].textContent.trim(),undefined,{sensitivity:"base",numeric:true}));rows.forEach((row,i)=>{tbody.append(row);const n=String(i+1);const desktop=row.querySelector(".user-desktop-number");const mobile=row.querySelector(".user-accordion-toggle span:nth-child(2)");if(desktop)desktop.textContent=n;if(mobile)mobile.textContent=n;});sortButton.textContent=ascending?"Name A–Z ↑":"Name Z–A ↓";}const mobileSort=document.createElement("button");mobileSort.type="button";mobileSort.className="mobile-name-sort";usersTable.parentElement.before(mobileSort);function toggleNames(){ascending=!ascending;sortNames()}sortButton.addEventListener("click",toggleNames);mobileSort.addEventListener("click",toggleNames);const originalSort=sortNames;sortNames=function(){originalSort();mobileSort.textContent=ascending?"Name A–Z ↑":"Name Z–A ↓"};sortNames();}</script></body></html>');
    }

    #[Route('/admin/users', name: 'admin_users', methods: ['GET', 'POST'])]
    public function users(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, CsrfTokenManagerInterface $csrfManager): Response
    {
        $driversView = $request->query->get('view') === 'drivers' || !$this->isGranted('ROLE_ADMIN');
        if ($driversView) {
            if (!SectionAccess::allowed($this->getUser(), 'rota')) throw $this->createAccessDeniedException();
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
            $role = (string) $request->request->get('role');
            $type = $role === 'ROLE_DRIVER' ? (string)$request->request->get('driver_type', 'DRIVER') : 'NONE';
            $password = (string) $request->request->get('password');
            $errors = [];
            if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username)) $errors[] = 'Username must be 3–80 characters and contain only lowercase letters, numbers, dots, underscores or hyphens.';
            if ($name === '') $errors[] = 'Full name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if ($phone === null) $errors[] = 'Enter a valid phone number for the selected country.';
            if (!in_array($role, $driversView ? ['ROLE_DRIVER'] : ['ROLE_DRIVER','ROLE_MANAGER','ROLE_ADMIN'], true)) $errors[] = 'Select a valid account type.';
            if (!in_array($type, $role === 'ROLE_DRIVER' ? ['DRIVER','JUMPER'] : ['NONE'], true)) $errors[] = 'Operational type does not match the account type.';
            if ($role === 'ROLE_DRIVER' ? !DriverPasswordPolicy::valid($password) : strlen($password) < 12) $errors[] = $role === 'ROLE_DRIVER' ? DriverPasswordPolicy::MESSAGE : 'Temporary password must contain at least 12 characters.';
            if ($em->getRepository(User::class)->findOneBy(['username' => $username])) $errors[] = 'This username is already in use.';
            if ($errors) {
                $message = '<div class="panel error" role="alert"><strong>Account could not be created:</strong><ul><li>'.implode('</li><li>',array_map(static fn($error)=>htmlspecialchars($error,ENT_QUOTES),$errors)).'</li></ul>Your details are preserved below; please correct the highlighted issues.</div>';
            } else {
                $user = (new User())->setUsername($username)->setDisplayName($name)->setEmail($email)->setPhone($phone ?: null)->setRoles([$role])->setDriverType($type);
                $user->setSections($this->isGranted('ROLE_ADMIN') && $role === 'ROLE_MANAGER' ? array_values(array_intersect($request->request->all('sections'), array_keys(SectionAccess::SECTIONS))) : []);
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
            $rows .= '<tr data-active="'.($user->isActive() ? 'yes' : 'no').'"><td>'.$rowNumber.'</td><td>'.htmlspecialchars($user->getUsername()).'</td><td>'.htmlspecialchars($user->getDisplayName()).'</td><td>'.htmlspecialchars($user->getEmail()).'</td><td>'.htmlspecialchars($user->getPhone() ?? '').'</td><td>'.htmlspecialchars(implode(', ', $user->getRoles())).'</td><td>'.htmlspecialchars($user->getDriverType()).'</td><td><span style="display:inline-block;padding:4px 10px;border-radius:999px;font-weight:700;background:'.($user->isActive() ? '#dcfce7;color:#166534' : '#fee2e2;color:#991b1b').'">'.($user->isActive() ? '● Active' : '● Inactive').'</span></td><td><div style="display:flex;align-items:stretch;gap:8px;white-space:nowrap"><a href="/admin/users/edit/'.$user->getId().'" style="display:inline-flex;align-items:center;justify-content:center;height:44px;box-sizing:border-box;padding:0 14px;background:#1a4c77;color:white;border-radius:4px;text-decoration:none;font:inherit">Edit</a> '.($this->isGranted('ROLE_ADMIN') && $user->getId() !== $this->getUser()?->getId() ? '<form method="post" style="margin:0;display:flex;align-items:center" action="/admin/users/delete/'.$user->getId().'"><input type="hidden" name="_token" value="'.htmlspecialchars($csrfManager->getToken('delete_user_'.$user->getId())->getValue()).'"><button type="submit" style="height:44px;box-sizing:border-box;margin:0;padding:0 14px;display:inline-flex;align-items:center;justify-content:center;font:inherit" onclick="return confirm(&quot;Delete this account permanently?&quot;)">Delete</button></form>' : '').'</div></td></tr>';
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
            $importPanel = '<div class="panel"><h2>Excel user import / export (administrators only)</h2><p>Download the existing-user template, complete every field for new users, and upload the XLSX file. Existing usernames are skipped without changing passwords.</p><p><a href="/admin/users/excel/export" style="display:inline-block;background:#1a4c77;color:white;padding:10px 14px;border-radius:5px;text-decoration:none">Export Excel Template</a></p><form action="/admin/users/excel/import" method="post" enctype="multipart/form-data"><input type="hidden" name="_token" value="'.$importToken.'"><label for="users-excel-file">Import completed Excel file (.xlsx)</label><input id="users-excel-file" type="file" name="excel_file" accept=".xlsx" required><button type="submit">Validate and Import Users</button></form><p><small>All nine columns are required for new accounts. For non-driver accounts use N/A for Operational Type; for non-managers use N/A for Manager Permissions. Managers use rota, vehicle and/or operations. Password = lowercase username + Password Suffix; Driver/Jumper passwords need 8+ characters, a number and a special character; Manager/Admin passwords need 12+ characters. Do not share completed templates containing password suffixes.</small></p></div>';
        }
        foreach ($request->getSession()->getFlashBag()->get('user_import',[]) as $notice) $message .= '<p class="ok">'.htmlspecialchars($notice,ENT_QUOTES).'</p>';
        $roleFilter = $driversView ? '' : '<label for="user-role-filter">Filter by role</label><select id="user-role-filter" style="max-width:260px;margin-bottom:12px"><option value="all">All roles</option><option value="ROLE_ADMIN">Administrator</option><option value="ROLE_MANAGER">Manager</option><option value="ROLE_DRIVER">Driver / Jumper</option></select>';
        $body = $message.$importPanel.'<div class="panel"><h2>Create user account</h2><form method="post"><input type="hidden" name="_token" value="'.htmlspecialchars($token).'"><label>Username</label><input name="username" value="'.$old('username').'" required minlength="3"><label>Full name</label><input name="name" value="'.$old('name').'" required><label>Email</label><input type="email" name="email" value="'.$old('email').'" required><label>Phone</label><div class="phone-row"><select name="country_code" aria-label="Country code">'.$this->phoneOptions($country).'</select><input name="phone" value="'.$old('phone').'" type="tel" inputmode="tel" placeholder="7700 900123"></div><label>Account type</label><select name="role"><option value="ROLE_DRIVER"'.($oldRole==='ROLE_DRIVER'?' selected':'').'>Driver</option>'.(!$driversView && $this->isGranted('ROLE_ADMIN') ? '<option value="ROLE_MANAGER"'.($oldRole==='ROLE_MANAGER'?' selected':'').'>Manager</option><option value="ROLE_ADMIN"'.($oldRole==='ROLE_ADMIN'?' selected':'').'>Administrator</option>' : '').'</select><div class="operational-type-field"><label>Operational type</label><select name="driver_type"><option value="DRIVER"'.($oldType==='DRIVER'?' selected':'').'>Driver</option><option value="JUMPER"'.($oldType==='JUMPER'?' selected':'').'>Jumper</option></select></div>'.(!$driversView && $this->isGranted('ROLE_ADMIN') ? '<fieldset id="manager-sections" style="display:none;margin:14px 0;border:1px solid #cbd7e4;border-radius:8px;padding:12px"><legend>Manager sections</legend><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="sections[]" value="rota"'.(in_array('rota',$oldSections,true)?' checked':'').' style="width:auto;margin:0"> Rota Management</label><label style="display:flex;align-items:center;gap:8px;margin-top:10px"><input type="checkbox" name="sections[]" value="vehicle"'.(in_array('vehicle',$oldSections,true)?' checked':'').' style="width:auto;margin:0"> Vehicle Management</label><label style="display:flex;align-items:center;gap:8px;margin-top:10px"><input type="checkbox" name="sections[]" value="operations"'.(in_array('operations',$oldSections,true)?' checked':'').' style="width:auto;margin:0"> Operations</label><small>Assign one or more application sections to this manager.</small></fieldset><script>document.addEventListener("DOMContentLoaded",()=>{const role=document.querySelector("select[name=role]");const type=document.querySelector("select[name=driver_type]");const sections=document.getElementById("manager-sections");if(!role||!sections)return;const sync=()=>{document.querySelector(".operational-type-field").style.display=role.value==="ROLE_DRIVER"?"block":"none";sections.style.display=role.value==="ROLE_MANAGER"?"block":"none";if(type&&!type.dataset.initialized){type.dataset.initialized="1";}};role.addEventListener("change",()=>{sync();if(type)type.value="DRIVER";});sync();});</script>' : '').'<label>Temporary password (Driver/Jumper: 8+ with number and special character; others: 12+)</label><input name="password" type="password" minlength="8" required autocomplete="new-password"><button>Create account</button></form></div><div class="panel"><h2>Existing users</h2><label for="user-search">Search by driver name or username</label><input id="user-search" type="search" placeholder="Search users…" >'.$roleFilter.($driversView ? '<style>#users-table th:nth-child(6),#users-table td:nth-child(6){display:none!important}</style>' : '').'<label for="user-active-filter">Filter by status</label><select id="user-active-filter" style="max-width:260px;margin-bottom:12px"><option value="all">All statuses</option><option value="yes">Active</option><option value="no">Inactive</option></select><div style="overflow:auto"><table id="users-table" data-drivers-view="'.($driversView ? '1' : '0').'"><thead><tr><th>#</th><th>Username</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody>'.$rows.'</tbody></table></div></div>';
        return $this->page($driversView ? 'Drivers' : 'User management', $body);
    }


    #[Route('/admin/users/edit/{id}', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editUser(int $id, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfManager): Response
    {
        if (!SectionAccess::allowed($this->getUser(), 'rota')) throw $this->createAccessDeniedException();
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
            $role = in_array('ROLE_ADMIN', $user->getRoles(), true) ? 'ROLE_ADMIN' : (in_array('ROLE_MANAGER', $user->getRoles(), true) ? 'ROLE_MANAGER' : 'ROLE_DRIVER');
            if ($request->request->has('role') && (string)$request->request->get('role') !== $role) throw $this->createAccessDeniedException('Account role cannot be changed after creation.');
            $type = $role === 'ROLE_DRIVER' ? (string)$request->request->get('driver_type') : 'NONE';
            $allowedRoles = $admin ? ['ROLE_DRIVER', 'ROLE_MANAGER', 'ROLE_ADMIN'] : ['ROLE_DRIVER'];
            if ($name === '' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $phone === null || !in_array($type,$role === 'ROLE_DRIVER' ? ['DRIVER','JUMPER'] : ['NONE'],true) || !in_array($role,$allowedRoles,true)) {
                $message='<p class="error">Check the account details and permissions.</p>';
            } else {
                $user->setDisplayName($name)->setEmail($email)->setPhone($phone ?: null)->setDriverType($type);
                if ($admin) $user->setSections($role === 'ROLE_MANAGER' ? array_values(array_intersect($request->request->all('sections'), array_keys(SectionAccess::SECTIONS))) : []);
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
            $driverControls = '<div class="panel"><h2>Driver access</h2><p>Status: <strong>'.($user->isActive() ? 'Active' : 'Inactive').'</strong></p><form method="post" action="/admin/users/status/'.$id.'"><input type="hidden" name="_token" value="'.$statusToken.'"><button type="submit">'.($user->isActive() ? 'Deactivate driver' : 'Reactivate driver').'</button></form><h2>Reset driver password</h2><form method="post" action="/admin/users/reset-password/'.$id.'"><input type="hidden" name="_token" value="'.$resetToken.'"><label>New temporary password (minimum 8 characters, including a number and special character)</label><input type="password" name="temporary_password" minlength="8" required autocomplete="new-password"><button type="submit">Set temporary password</button></form></div>';
        }
        if ($admin && $roleLabel !== 'Driver') {
            $resetToken = $e($csrfManager->getToken('reset_driver_'.$id)->getValue());
            $driverControls .= '<div class="panel"><h2>Reset account password</h2><p>Minimum 12 characters.</p><form method="post" action="/admin/users/reset-password/'.$id.'"><input type="hidden" name="_token" value="'.$resetToken.'"><label>New temporary password</label><input type="password" name="temporary_password" minlength="12" required autocomplete="new-password"><button type="submit">Set temporary password</button></form></div>';
        }
        $managerSections = '';
        if ($admin) {
            $checked = in_array('rota', $user->getSections(), true) ? ' checked' : '';
            $vehicleChecked = in_array('vehicle', $user->getSections(), true) ? ' checked' : '';
            $operationsChecked = in_array('operations', $user->getSections(), true) ? ' checked' : '';
            $managerSections = '<fieldset id="manager-sections" style="margin:14px 0;border:1px solid #cbd7e4;border-radius:8px;padding:12px"><legend>Manager sections</legend><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="sections[]" value="rota"'.$checked.' style="width:auto;margin:0"> Rota Management</label><label style="display:flex;align-items:center;gap:8px;margin-top:10px"><input type="checkbox" name="sections[]" value="vehicle"'.$vehicleChecked.' style="width:auto;margin:0"> Vehicle Management</label><label style="display:flex;align-items:center;gap:8px;margin-top:10px"><input type="checkbox" name="sections[]" value="operations"'.$operationsChecked.' style="width:auto;margin:0"> Operations</label><small>Assign the application sections this manager can access.</small></fieldset>';
        }
        return $this->page('Edit user', $message.$driverControls.($roleLabel==='Driver'?'<div class="panel"><h2>Training &amp; requirements</h2><p>Track WISE, Cognition, Ementor, Clear Quote, Badge and any new requirements. Each completion records who checked it and when.</p><div id="driver-training-checklist" data-url="/admin/users/'.$id.'/requirements"><p>Loading requirements…</p></div><style>#driver-training-checklist table{width:100%;border-collapse:collapse}#driver-training-checklist th,#driver-training-checklist td{padding:12px;text-align:left;border-bottom:1px solid #e1e7ef}#driver-training-checklist th{background:#edf3fa}#driver-training-checklist .requirement-toggle{width:24px;height:24px;cursor:pointer;accent-color:#16734a;margin:0}#driver-training-checklist .requirement-toggle:focus-visible{outline:3px solid #7fb5ff;outline-offset:3px}#driver-training-checklist details{margin-top:18px;padding:12px;border:1px solid #dbe3ee;border-radius:8px}#driver-training-checklist summary{cursor:pointer;font-weight:600}#driver-training-checklist .training-status{color:#52687e;font-size:13px}#driver-training-checklist .training-error{color:#a51e26}</style><script>document.addEventListener("DOMContentLoaded",()=>{const root=document.getElementById("driver-training-checklist");if(!root)return;const url=root.dataset.url;async function load(){try{const response=await fetch(url,{credentials:"same-origin"});if(!response.ok)throw Error("Unable to load checklist");const doc=new DOMParser().parseFromString(await response.text(),"text/html");const main=doc.querySelector("main");if(!main)throw Error("Checklist unavailable");const card=main.querySelector(".card");const history=main.querySelector("details");if(!card)throw Error("Checklist unavailable");root.replaceChildren(card);if(history)root.append(history);root.querySelectorAll("form").forEach(form=>{form.action=url;const button=form.querySelector("button");if(!button)return;const checked=button.textContent.trim()==="☑";const input=document.createElement("input");input.type="checkbox";input.className="requirement-toggle";input.checked=checked;input.setAttribute("aria-label",checked?"Mark requirement incomplete":"Mark requirement complete");input.addEventListener("change",async()=>{const label=form.closest("tr")?.querySelector("td:nth-child(2)")?.textContent?.trim()||"this requirement";const action=input.checked?"mark as completed":"mark as incomplete";if(!confirm("Do you really want to "+action+" "+label+"?\\n\\nThis action will be logged with your name and the date/time. The driver training history is retained for at least 12 months.")){input.checked=!input.checked;return}input.disabled=true;try{const result=await fetch(url,{method:"POST",body:new FormData(form),credentials:"same-origin"});if(!result.ok)throw Error("Could not save change");await load()}catch(error){input.disabled=false;input.checked=!input.checked;alert(error.message)}});button.replaceWith(input)})}catch(error){root.innerHTML="<p class=training-error>Could not load the training checklist. <a href=\""+url+"\">Open it separately</a>.</p>"}}load()})</script></div>':'').'<div class="panel"><form method="post"><input type="hidden" name="_token" value="'.$e($csrfManager->getToken('edit_user_'.$id)->getValue()).'"><p>Username: <b>'.$e($user->getUsername()).'</b></p><label>Full name</label><input name="name" value="'.$e($user->getDisplayName()).'" required><label>Email</label><input name="email" type="email" value="'.$e($user->getEmail()).'" required><label>Phone</label><div class="phone-row"><select name="country_code">'.$this->phoneOptions($country).'</select><input name="phone" value="'.$e($local).'"></div><label>Account role</label><p><strong>'.$e($roleLabel).' 🔒</strong></p><p style="color:#64748b;font-size:13px">Account role is permanent. Create a new account for a different role; deactivate the old Driver account to preserve history.</p><div class="operational-type-field"><label>Operational type</label><select name="driver_type">'.$options.'</select></div>'.$managerSections.'<script>document.addEventListener("DOMContentLoaded",()=>{const field=document.querySelector(".operational-type-field");const sections=document.getElementById("manager-sections");if(field)field.style.display="'.($roleLabel==='Driver'?'block':'none').'";if(sections)sections.style.display="'.($roleLabel==='Manager'?'block':'none').'"})</script><br><button>Save account</button> <a href="/admin/users">Cancel</a></form></div>');
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
        $em->remove($user);
        $em->flush();
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
        return $this->page('My account', $message.'<div class="panel"><form method="post"><input type="hidden" name="_token" value="'.$e($token).'"><p>Username: <b>'.$e($user->getUsername()).'</b></p>'.($driverOnly ? '<p><strong>Full name:</strong> '.$e($user->getDisplayName()).'</p><p><strong>Email:</strong> '.$e($user->getEmail()).'</p><p><strong>Phone:</strong> '.$e($user->getPhone() ?: 'Not provided').'</p><p>To update these details, contact your manager or administrator.</p>' : '<label>Full name</label><input name="name" required value="'.$e($user->getDisplayName()).'">').($driverOnly ? '<h2>Change password</h2>' : '<label>Email</label><input name="email" type="email" required value="'.$e($user->getEmail()).'"><label>Phone</label><div class="phone-row"><select name="country_code" aria-label="Country code">'.$this->phoneOptions($countryCode).'</select><input name="phone" type="tel" inputmode="tel" placeholder="7700 900123" value="'.$e($localPhone).'"></div><h2>Change password (optional)</h2>').'<label>Current password</label><input name="current_password" type="password" autocomplete="current-password"><label>New password</label><p>'.($driverOnly ? 'Minimum 8 characters, including at least 1 number and 1 special character (e.g. @, #, !).' : 'Minimum 12 characters.').'</p><input name="new_password" type="password" autocomplete="new-password" minlength="'.($driverOnly ? '8' : '12').'"><br><button>Save changes</button></form></div>');
    }
}
