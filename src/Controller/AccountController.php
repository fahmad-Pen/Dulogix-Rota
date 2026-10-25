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
        return new Response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dulogix · '.htmlspecialchars($title).'</title><link rel="icon" type="image/png" href="/favicon.png"><style>*{box-sizing:border-box}body{font:13px system-ui;background:#f5f7fb;color:#24364b;margin:0}header{background:#152d49;color:white;padding:12px 20px}header img{height:32px;width:auto;vertical-align:middle;margin-right:16px;background:white;border-radius:4px;padding:2px}header a{color:#d6e8fc;margin-right:18px}main{max-width:1080px;margin:16px auto;padding:0 12px}.panel{background:white;border:1px solid #e0e7ef;border-radius:7px;padding:14px;margin-bottom:12px}h1{font-size:18px;margin:0 0 12px}h2{font-size:14px}label{display:block;font-weight:600;margin:8px 0 4px}input,select{padding:7px;border:1px solid #cbd7e4;border-radius:4px;width:100%;max-width:420px}.phone-row{display:flex;gap:8px;width:100%;max-width:420px}.phone-row select{width:195px;flex:0 0 195px;min-width:0}.phone-row input{width:0;min-width:0;flex:1 1 auto}button{padding:8px 12px;border:0;border-radius:4px;background:#1a4c77;color:white;cursor:pointer;margin-top:12px}table{width:100%;border-collapse:collapse}th,td{padding:7px;text-align:left;border-bottom:1px solid #e5eaf1}tr:nth-child(even){background:#f1f5fa}.error{color:#b22e36}.ok{color:#13774b}</style></head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a> '.($this->isGranted('ROLE_OPERATIONS') ? '<a href="/">Rota</a><a href="/admin/users">Users</a>' : '').($this->isGranted('ROLE_DRIVER') ? '<a href="/driver/availability">My availability</a>' : '').($this->isGranted('ROLE_ADMIN') ? '<a href="/driver/availability?preview=1">Driver preview</a>' : '').'<a href="/account">My account</a><a href="/logout">Sign out</a></header><main><h1>'.htmlspecialchars($title).'</h1>'.$body.'</main></body></html>');
    }

    #[Route('/admin/users', name: 'admin_users', methods: ['GET', 'POST'])]
    public function users(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, CsrfTokenManagerInterface $csrfManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
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
            $type = (string) $request->request->get('driver_type', 'DRIVER');
            $password = (string) $request->request->get('password');
            if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username) || $name === '' || $phone === null || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $this->isGranted('ROLE_ADMIN') ? ['ROLE_DRIVER', 'ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_USER'] : ['ROLE_DRIVER'], true) || !in_array($type, ['MANAGER','DRIVER','JUMPER'], true) || ($type === 'MANAGER' && $role !== 'ROLE_MANAGER') || strlen($password) < 12 || $em->getRepository(User::class)->findOneBy(['username' => $username]) || $em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $message = '<p class="error">Check fields: unique username/email, password at least 12 characters, valid role.</p>';
            } else {
                $user = (new User())->setUsername($username)->setDisplayName($name)->setEmail($email)->setPhone($phone ?: null)->setRoles([$role])->setDriverType($type);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();
                $message = '<p class="ok">Account created.</p>';
            }
        }
        $rows = '';
        foreach ($em->getRepository(User::class)->findBy([], ['id' => 'ASC']) as $user) {
            $rows .= '<tr><td>'.htmlspecialchars($user->getUsername()).'</td><td>'.htmlspecialchars($user->getDisplayName()).'</td><td>'.htmlspecialchars($user->getEmail()).'</td><td>'.htmlspecialchars($user->getPhone() ?? '').'</td><td>'.htmlspecialchars(implode(', ', $user->getRoles())).'</td><td>'.htmlspecialchars($user->getDriverType()).'</td><td><a href="/admin/users/edit/'.$user->getId().'" style="display:inline-block;padding:8px 12px;margin-right:8px;background:#1a4c77;color:white;border-radius:4px;text-decoration:none">Edit</a> '.($this->isGranted('ROLE_ADMIN') && $user->getId() !== $this->getUser()?->getId() ? '<form method="post" action="/admin/users/delete/'.$user->getId().'"><input type="hidden" name="_token" value="'.htmlspecialchars($csrfManager->getToken('delete_user_'.$user->getId())->getValue()).'"><button type="submit" onclick="return confirm(&quot;Delete this account permanently?&quot;)">Delete</button></form>' : '').'</td></tr>';
        }
        $token = $csrfManager->getToken('create_user')->getValue();
        $body = $message.'<div class="panel"><h2>Create user account</h2><form method="post"><input type="hidden" name="_token" value="'.htmlspecialchars($token).'"><label>Username</label><input name="username" required minlength="3"><label>Full name</label><input name="name" required><label>Email</label><input type="email" name="email" required><label>Phone</label><div class="phone-row"><select name="country_code" aria-label="Country code">'.$this->phoneOptions().'</select><input name="phone" type="tel" inputmode="tel" placeholder="7700 900123"></div><label>Account type</label><select name="role"><option value="ROLE_DRIVER">Driver</option>'.($this->isGranted('ROLE_ADMIN') ? '<option value="ROLE_USER">User</option>' : '').($this->isGranted('ROLE_ADMIN') ? '<option value="ROLE_MANAGER">Manager</option><option value="ROLE_ADMIN">Administrator</option>' : '').'</select><label>Operational type</label><select name="driver_type"><option value="DRIVER">Driver</option><option value="JUMPER">Jumper</option>'.($this->isGranted('ROLE_ADMIN') ? '<option value="MANAGER">Manager</option>' : '').'</select><label>Temporary password (12+ characters)</label><input name="password" type="password" minlength="12" required autocomplete="new-password"><button>Create account</button></form></div><div class="panel"><h2>Existing users</h2><div style="overflow:auto"><table><thead><tr><th>Username</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Type</th><th>Actions</th></tr></thead><tbody>'.$rows.'</tbody></table></div></div>';
        return $this->page('User management', $body);
    }


    #[Route('/admin/users/edit/{id}', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editUser(int $id, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrfManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OPERATIONS');
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
            $type = (string) $request->request->get('driver_type');
            $role = (string) $request->request->get('role');
            $allowedRoles = $admin ? ['ROLE_DRIVER', 'ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_USER'] : ['ROLE_DRIVER'];
            $duplicate = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($name === '' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $phone === null || !in_array($type,['DRIVER','JUMPER','MANAGER'],true) || !in_array($role,$allowedRoles,true) || ($type === 'MANAGER' && $role !== 'ROLE_MANAGER') || ($duplicate && $duplicate->getId()!==$id)) {
                $message='<p class="error">Check the account details and permissions.</p>';
            } else {
                $user->setDisplayName($name)->setEmail($email)->setPhone($phone ?: null)->setDriverType($type)->setRoles([$role]);
                $em->flush();
                $message='<p class="ok">Account updated.</p>';
            }
        }
        $e=static fn(?string $v)=>htmlspecialchars($v??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        [$country,$local]=$this->phoneParts($user->getPhone());
        $options='';
        foreach (['DRIVER'=>'Driver','JUMPER'=>'Jumper','MANAGER'=>'Manager'] as $value=>$label) {
            if ($value==='MANAGER' && !$admin) continue;
            $options.='<option value="'.$value.'"'.($user->getDriverType()===$value?' selected':'').'>'.$label.'</option>';
        }
        $roles='';
        foreach (['ROLE_DRIVER'=>'Driver','ROLE_MANAGER'=>'Manager','ROLE_ADMIN'=>'Administrator','ROLE_USER'=>'User'] as $value=>$label) {
            if (!$admin && $value!=='ROLE_DRIVER') continue;
            $roles.='<option value="'.$value.'"'.(in_array($value,$user->getRoles(),true)?' selected':'').'>'.$label.'</option>';
        }
        return $this->page('Edit user', $message.'<div class="panel"><form method="post"><input type="hidden" name="_token" value="'.$e($csrfManager->getToken('edit_user_'.$id)->getValue()).'"><p>Username: <b>'.$e($user->getUsername()).'</b></p><label>Full name</label><input name="name" value="'.$e($user->getDisplayName()).'" required><label>Email</label><input name="email" type="email" value="'.$e($user->getEmail()).'" required><label>Phone</label><div class="phone-row"><select name="country_code">'.$this->phoneOptions($country).'</select><input name="phone" value="'.$e($local).'"></div><label>Account role</label><select name="role">'.$roles.'</select><label>Operational type</label><select name="driver_type">'.$options.'</select><br><button>Save account</button> <a href="/admin/users">Cancel</a></form></div>');
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
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_profile', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $phone = $this->phoneValue($request);
            $name = trim((string) $request->request->get('name'));
            $newPassword = (string) $request->request->get('new_password');
            $currentPassword = (string) $request->request->get('current_password');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === null || $name === '' || mb_strlen($name) > 120 || mb_strlen($phone) > 40 || ($newPassword !== '' && strlen($newPassword) < 12) || ($newPassword !== '' && !$hasher->isPasswordValid($user, $currentPassword)) || (($existing = $em->getRepository(User::class)->findOneBy(['email' => $email])) && $existing->getId() !== $user->getId())) {
                $message = '<p class="error">Check your details. New passwords need 12+ characters and your current password.</p>';
            } else {
                $user->setEmail($email)->setPhone($phone ?: null)->setDisplayName($name);
                if ($newPassword !== '') $user->setPassword($hasher->hashPassword($user, $newPassword));
                $em->flush();
                $message = '<p class="ok">Details saved.</p>';
            }
        }
        $e = static fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        [$countryCode, $localPhone] = $this->phoneParts($user->getPhone());
        $token = $csrfManager->getToken('edit_profile')->getValue();
        return $this->page('My account', $message.'<div class="panel"><form method="post"><input type="hidden" name="_token" value="'.$e($token).'"><p>Username: <b>'.$e($user->getUsername()).'</b></p><label>Full name</label><input name="name" required value="'.$e($user->getDisplayName()).'"><label>Email</label><input name="email" type="email" required value="'.$e($user->getEmail()).'"><label>Phone</label><div class="phone-row"><select name="country_code" aria-label="Country code">'.$this->phoneOptions($countryCode).'</select><input name="phone" type="tel" inputmode="tel" placeholder="7700 900123" value="'.$e($localPhone).'"></div><h2>Change password (optional)</h2><label>Current password</label><input name="current_password" type="password" autocomplete="current-password"><label>New password</label><input name="new_password" type="password" autocomplete="new-password" minlength="12"><br><button>Save changes</button></form></div>');
    }
}
