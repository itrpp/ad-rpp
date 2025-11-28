<?php
namespace frontend\controllers;
use yii\helpers\Html;



$this->title = 'User Details';
$this->params['breadcrumbs'][] = ['label' => 'Ldap', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<h1><?= Html::encode($this->title) ?></h1>

<ul>
    <!-- <li><strong>CN:</strong> <?= Html::encode($user['cn'][0]) ?></li>
    <li><strong>SN:</strong> <?= Html::encode($user['sn'][0]?? 'N/A') ?></li>
    <li><strong>primarygroupid:</strong> <?= Html::encode($user['primarygroupid'][0]?? 'N/A') ?></li>
    <li><strong>distinguishedname:</strong> <?= Html::encode($user['distinguishedname'][0]?? 'N/A') ?></li>
    <li><strong>company:</strong> <?= Html::encode($user['company'][0]?? 'N/A') ?></li>
    <li><strong>displayName:</strong> <?= Html::encode($user['displayname'][0]?? 'N/A') ?></li>
    <li><strong>primaryGroupID:</strong> <?= Html::encode($user['primarygroupid'][0]?? 'N/A') ?></li>
    <li><strong>userPrincipalName:</strong> <?= Html::encode($user['userprincipalname'][0]?? 'N/A') ?></li>
    <li><strong>uSNChanged:</strong> <?= Html::encode($user['usnChanged'][0]?? 'N/A') ?></li>
    <li><strong>whenChanged:</strong> <?= Html::encode($user['whenchanged'][0]?? 'N/A') ?></li>
    <li><strong>whenCreated:</strong> <?= Html::encode($user['whencreated'][0]?? 'N/A') ?></li>
    <li><strong>distinguishedname:</strong> <?= Html::encode($user['distinguishedname'][0]?? 'N/A') ?></li>
    <li><strong>Email:</strong> <?= Html::encode($user['mail'][0] ?? 'N/A') ?></li>
    <li><strong>Name:</strong> <?= Html::encode($user['name'][0] ?? 'N/A') ?></li>
    <li><strong>objectClass:</strong> <?= Html::encode($user['objectclass'][0] ?? 'N/A') ?></li>
    <li><strong>sAMAccountName:</strong> <?= Html::encode($user['samaccountname'][0] ?? 'N/A') ?></li>
    <li><strong>givenName:</strong> <?= Html::encode($user['givenName'][0] ?? 'N/A') ?></li>
    <li><strong>logoncount:</strong> <?= Html::encode($user['logoncount'][0] ?? 'N/A') ?></li>
    <li><strong>department:</strong> <?= Html::encode($user['department'][0] ?? 'N/A') ?></li> -->

    <li><strong>accountexpires:</strong> <?= Html::encode($user['accountexpires'][0]?? 'N/A') ?></li>
    <li><strong>badpasswordtime:</strong> <?= Html::encode($user['badpasswordtime'][0]?? 'N/A') ?></li>
    <li><strong>badpwdcount:</strong> <?= Html::encode($user['badpwdcount'][0]?? 'N/A') ?></li>
    <li><strong>c:</strong> <?= Html::encode($user['c'][0]?? 'N/A') ?></li>
    <li><strong>cn:</strong> <?= Html::encode($user['cn'][0]?? 'N/A') ?></li>
    <li><strong>co:</strong> <?= Html::encode($user['co'][0]?? 'N/A') ?></li>
    <li><strong>codepage:</strong> <?= Html::encode($user['codepage'][0]?? 'N/A') ?></li>
    <li><strong>company:</strong> <?= Html::encode($user['company'][0]?? 'N/A') ?></li>
    <li><strong>countrycode:</strong> <?= Html::encode($user['countrycode'][0]?? 'N/A') ?></li>
    <li><strong>department:</strong> <?= Html::encode($user['department'][0]?? 'N/A') ?></li>
    <li><strong>description:</strong> <?= Html::encode($user['description'][0]?? 'N/A') ?></li>
    <li><strong>displayname:</strong> <?= Html::encode($user['displayname'][0]?? 'N/A') ?></li>
    <li><strong>givenname:</strong> <?= Html::encode($user['givenname'][0]?? 'N/A') ?></li>
    <li><strong>initials:</strong> <?= Html::encode($user['initials'][0]?? 'N/A') ?></li>
    <li><strong>instancetype:</strong> <?= Html::encode($user['instancetype'][0]?? 'N/A') ?></li>
    <li><strong>l:</strong> <?= Html::encode($user['l'][0]?? 'N/A') ?></li>
    <li><strong>lastlogoff:</strong> <?= Html::encode($user['lastlogoff'][0]?? 'N/A') ?></li>
    <li><strong>lastlogon:</strong> <?= Html::encode($user['lastlogon'][0]?? 'N/A') ?></li>
    <li><strong>lastlogontimestamp:</strong> <?= Html::encode($user['lastlogontimestamp'][0]?? 'N/A') ?></li>
    <li><strong>logoncount:</strong> <?= Html::encode($user['logoncount'][0]?? 'N/A') ?></li>
    <li><strong>mail:</strong> <?= Html::encode($user['mail'][0]?? 'N/A') ?></li>
    <li><strong>msds-supportedencryptiontypes:</strong> <?= Html::encode($user['msds-supportedencryptiontypes'][0]?? 'N/A') ?></li>
    <li><strong>name:</strong> <?= Html::encode($user['name'][0]?? 'N/A') ?></li>
    <li><strong>objectguid:</strong> <?= Html::encode($user['objectguid'][0]?? 'N/A') ?></li>
    <li><strong>objectsid:</strong> <?= Html::encode($user['objectsid'][0]?? 'N/A') ?></li>
    <li><strong>physicaldeliveryofficename:</strong> <?= Html::encode($user['physicaldeliveryofficename'][0]?? 'N/A') ?></li>
    <li><strong>postalcode:</strong> <?= Html::encode($user['postalcode'][0]?? 'N/A') ?></li>  
    <li><strong>postofficebox:</strong> <?= Html::encode($user['postofficebox'][0]?? 'N/A') ?></li>
    <li><strong>primarygroupid:</strong> <?= Html::encode($user['primarygroupid'][0]?? 'N/A') ?></li>
    <li><strong>pwdlastset:</strong> <?= Html::encode($user['pwdlastset'][0]?? 'N/A') ?></li>
    <li><strong>samaccountname:</strong> <?= Html::encode($user['samaccountname'][0]?? 'N/A') ?></li>
    <li><strong>samaccounttype:</strong> <?= Html::encode($user['samaccounttype'][0]?? 'N/A') ?></li>  
    <li><strong>title:</strong> <?= Html::encode($user['title'][0]?? 'N/A') ?></li>
    <li><strong>st:</strong> <?= Html::encode($user['st'][0]?? 'N/A') ?></li>
    <li><strong>SN:</strong> <?= Html::encode($user['sn'][0]?? 'N/A') ?></li>
    <li><strong>streetaddress:</strong> <?= Html::encode($user['streetaddress'][0]?? 'N/A') ?></li>
    <li><strong>telephonenumber:</strong> <?= Html::encode($user['telephonenumber'][0]?? 'N/A') ?></li>
    <li><strong>useraccountcontrol:</strong> <?= Html::encode($user['useraccountcontrol'][0]?? 'N/A') ?></li>
    <li><strong>userprincipalname:</strong> <?= Html::encode($user['userprincipalname'][0]?? 'N/A') ?></li>
    <li><strong>usnchanged:</strong> <?= Html::encode($user['usnchanged'][0]?? 'N/A') ?></li>
    <li><strong>usncreated:</strong> <?= Html::encode($user['usncreated'][0]?? 'N/A') ?></li>
    <li><strong>whenchanged:</strong> <?= Html::encode($user['whenchanged'][0]?? 'N/A') ?></li>
    <li><strong>whencreated:</strong> <?= Html::encode($user['whencreated'][0]?? 'N/A') ?></li>
    <li><strong>wwwhomepage:</strong> <?= Html::encode($user['wwwhomepage'][0]?? 'N/A') ?></li>
    <li><strong>distinguishedname:</strong> <?= Html::encode($user['distinguishedname'][0]?? 'N/A') ?></li>
    <li><strong>accountexpires:</strong> <?= Html::encode($user['accountexpires'][0]?? 'N/A') ?></li>
    <li><strong>SN:</strong> <?= Html::encode($user['objectclass'][0]?? 'N/A') ?></li>
    <li><strong>SN:</strong> <?= Html::encode($user['objectclass'][1]?? 'N/A') ?></li>
    <li><strong>SN:</strong> <?= Html::encode($user['objectclass'][2]?? 'N/A') ?></li>
    <li><strong>SN:</strong> <?= Html::encode($user['objectclass'][3]?? 'N/A') ?></li>


</ul>


<p><?= Html::a('Back', ['index'], ['class' => 'btn btn-default']) ?></p>
