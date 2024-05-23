<?

call_user_func(function () {
    global $DB, $USER_ID, $USER_FIRST_NAME, $USER_FONT_SCALE, $title, $PAGE_NAME, $ENTITY, $extraBodyClasses, $extraStylesheets, $extraScripts, $noJS, $primaryFilterIdField, $cobrandingLogoUrl;

    if (!isset($extraStylesheets)) $extraStylesheets=array();
    if (!is_array($extraStylesheets)) $extraStylesheets=array($extraStylesheets);

    $checkForStylesheet = '/stylesheets/'.$ENTITY.'.scss';
    if (file_exists( SITE_BASE_DIR.'/www'.$checkForStylesheet )) {
        $checkForStylesheet = '/stylesheets/'.$ENTITY.'.css';
        array_unshift($extraStylesheets, $checkForStylesheet);
    }

    $favicon = getConfig('Shortcut Icon');
    if (!isset($cobrandingLogoUrl)) $cobrandingLogoUrl = getConfig('Cobranding logo URL');
    ?>
    <!DOCTYPE html>
    <html lang="en" class="no-js">
    <head>
        <script>document.documentElement.classList.remove('no-js');</script>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">

        <? if (isset($title)) { ?>
            <title><?=htmlspecialchars($title)?></title>
        <? } ?>

        <link rel="stylesheet" href="https://use.typekit.net/crp4ibc.css">
        <?
            $fontScaleFactor = (int)getConfig('Font scale factor');
            if ($fontScaleFactor<10 || $fontScaleFactor >  255) $fontScaleFactor=100;
            if ($USER_FONT_SCALE>10 && $USER_FONT_SCALE < 256) $fontScaleFactor *= $USER_FONT_SCALE/100;
            if ($fontScaleFactor) { ?>
            <style>
                :root {
                    --fontScaleFactor : <?=(int)$fontScaleFactor/100?>;
                }
            </style>
        <? } ?>
        <link rel="stylesheet" type="text/css" href="/stylesheets/main.css">

        <? if (strlen($favicon)) { ?>
            <link rel="shortcut icon" type="image/x-icon" href="<?=htmlspecialchars($favicon)?>">
        <? } ?>
        <? foreach($extraStylesheets as $src){
            echo '<link rel="stylesheet" type="text/css" href="'.htmlspecialchars($src).'">';
        } ?>

		<? if(!isset($noJS) || $noJS !== true) { ?>
			<script src="/javascript/jquery.min.js"></script>
			<script src="/javascript/core/tools.js"></script>
			<script src="/javascript/renderPage.js"></script>
			<script src="/javascript/main.js"></script>
            <script src="/javascript/micromodal.min.js"></script>
            <script src="/javascript/micromodalWrapper.js"></script>
            <link rel="stylesheet" type="text/css" href="/stylesheets/modal.css">
            <? if (isset($extraScripts)) {
                foreach($extraScripts as $src){
                    echo "<script src=\"$src?".round(time()/86400)."\"></script>\n";
                }
            }
        } ?>
        <? globalHeaderMarkup(); ?>
    </head>
    <body class="<?=$ENTITY?> <?=htmlspecialchars($PAGE_NAME)?> <?=isset($extraBodyClasses)?htmlspecialchars(implode(' ',$extraBodyClasses)):''?> ">
    <? if (defined('IOD_ROLE') && IOD_ROLE=='master' && $USER_ID) { ?>
    <div style="width: 100%; text-align:center; font-weight: bold; border 2px solid red; background-color: #ffcccc; padding:10px;"><?=cms('THIS INSTANCE IS USED AS THE MODEL FOR INSTANCE-ON-DEMAND INSTANCES - DO NOT ENTER ANY LIVE DATA',1);?></div>
    <? } ?>
    <header class="site-header">
        <div class="primary-menu-container body-pad">
            <div class="top-row">
                <ul class="logos">
                    <? if (!empty($cobrandingLogoUrl)) { ?>
                        <li><a href="/"><img src="<?=htmlspecialchars($cobrandingLogoUrl)?>" alt=""></a></li>
                    <? } ?>
                    <li><a href="/"><img src="/images/ampletracks-logo.svg" alt="Ampletracks logo"></a></li>
                </ul>
                <!-- This button is only visible in mobile view -->
                <button class="primary-menu-button toggle-menu__toggle btn" aria-label="Primary menu" aria-expanded="false">
                    <?= getSVGIcon('menuDots') ?>
                </button>
            </div>

            <!-- Primary Menu -->
            <nav class="toggle-menu__menu">
                <ul class="primary-menu">
                    <? if($USER_ID) { ?>
                        <? if(isset($primaryFilterIdField)) { ?>
                            <li id="recordTypeSwitcher" class="toggle-menu">
                                <?
                                $recordTypes = $DB->getHash('
                                    SELECT recordType.id, recordType.name
                                    FROM recordType
                                    INNER JOIN userRecordType ON userRecordType.recordTypeId = recordType.id AND userRecordType.userId = ?
                                    LEFT JOIN user ON user.id = userRecordType.userId AND user.recordTypeFilter = recordType.id
                                    WHERE recordType.deletedAt = 0
                                    ORDER BY ISNULL(user.id) DESC, recordType.name
                                ',$USER_ID);
                                ?>
                                <button class="current-selection with-icon toggle-menu__toggle">
                                    <?= getSVGIcon('menuDownBoxArrow') ?>
                                    <span>Currently viewing <strong><?=array_pop($recordTypes)?></strong></span>
                                </button>
                                <ul class="toggle-menu__menu">
                                    <? foreach($recordTypes as $id => $name) { ?>
                                        <li data-recordTypeId="<?=htmlspecialchars($id)?>"><a href="#"><?=cms('Main Nav: Swith Record Type',0,'Switch to')?> <?=$name?></a></li>
                                    <? } ?>
                                </ul>
                                <script>
                                    $(function(){
                                        var filterForm = $('#filterForm');
                                        if (!filterForm.length) {
                                            $('#recordTypeFilterChange').remove();
                                        }
                                        $('#recordTypeSwitcher').on('click', 'li', function () {
                                            if (!filterForm.length) {
                                                // If there is an error the filter form might not get rendered
                                                // in that case just put it on the get string
                                                window.location.href='?recordTypeFilterChange='+$(this).attr('data-recordTypeId');
                                            } else {
                                                filterForm.find('[name=recordTypeFilterChange]').remove();
                                                $('<input type="hidden" name="recordTypeFilterChange"/>').val($(this).attr('data-recordTypeId')).prependTo(filterForm);
                                                filterForm.submit();
                                                return false;
                                            }
                                        });
                                    });
                                </script>
                            </li>
                        <? } ?>
                        <? if(strpos($_SERVER["PHP_SELF"], '/scanQRCode.php') !== 0) {?>
                            <li>
                                <a href="/scanQRCode.php">
                                    <?= getSVGIcon('scanLabel') ?>
                                    <?=cms('Main Nav: Scan Label',0,'Scan label')?>
                                </a>
                            </li>
                        <? } ?>
                        <li class="toggle-menu">
                            <button class="toggle-menu__toggle with-icon my-acocunt">
                                <?= getSVGIcon('myAccount') ?>
                                <span>My account</span>
                            </button>
                            <ul class="toggle-menu__menu">
                                <li>
                                    <a href="/user/admin.php?id=<?=(int)$USER_ID?>"><?=cms('Main Nav: Settings', 0, 'Settings')?></a>
                                </li>
                                <li>
                                    <a href="/login.php?mode=logout"><?=cms('Main Nav: Logout', 0, 'Logout')?></a>
                                </li>
                            </ul>
                        </li>
                    <? } else { ?>
                        <? if(strpos($_SERVER["PHP_SELF"], '/scanQRCode.php') !== 0) {?>
                            <li>
                                <a href="/scanQRCode.php">
                                    <?= getSVGIcon('scanLabel') ?>
                                    <?=cms('Main Nav: Scan Label',0,'Scan label')?>
                                </a>
                            </li>
                        <? } ?>
                    <? } ?>
                </ul>
            </nav>
        </div>

        <? if($USER_ID) { ?>
            <!-- Secondary Menu -->
            <div class="secondary-menu-container body-pad bg-d">
                <nav class="toggle-menu-secondary container">
                    <ul class="secondary-menu toggle-menu__menu">
                        <? if(canDo('list', 'recordTypeId')) { ?>
                            <li>
                                <a href="/record/list.php"><?=cms('Main Nav: Records', 0, 'Records')?></a>
                            </li>
                        <? } ?>
                        <? if (canDoMultiple('list', 'dataField,recordType,relationship', 'or')) { ?>
                            <li class="toggle-menu">
                                <button class="toggle-menu__toggle">
                                    <?= getSVGIcon('menuDownArrow') ?>
                                    <span><?=cms('Main Nav: Shema', 0, 'Schema')?></spam>
                                </button>
                                <ul class="toggle-menu__menu">
                                    <? if(canDo('list', 'dataField')) { ?>
                                        <li>
                                            <a href="/dataField/list.php"><?=cms('Main Nav: Data Fields', 0, 'Data Fields')?></a>
                                        </li>
                                    <? } ?>
                                    <? if (canDo('list','recordType')) { ?>
                                        <li>
                                            <a href="/recordType/list.php"><?=cms('Main Nav: Record Types', 0, 'Record Types')?></a>
                                        </li>
                                    <? } ?>
                                    <? if (canDo('list', 'relationship')) { ?>
                                        <li>
                                            <a href="/relationshipPair/list.php"><?=cms('Main Nav: Relationships', 0, 'Relationships')?></a>
                                        </li>
                                    <? } ?>
                                </ul>
                            </li>
                        <? } ?>
                        <? if (canDoMultiple('list', 'project,user,role,cms,configuration', 'or')) { ?>
                            <li class="toggle-menu">
                                <button class="toggle-menu__toggle">
                                    <?= getSVGIcon('menuDownArrow') ?>
                                    <span><?=cms('Main Nav: Admin', 0, 'Admin')?></span>
                                </button>
                                <ul class="toggle-menu__menu">
                                    <? if (canDo('list', 'project')) { ?>
                                        <li>
                                            <a href="/project/list.php"><?=cms('Main Nav: Projects', 0, 'Projects')?></a>
                                        </li>
                                    <? } ?>
                                    <? if (canDo('list', 'user')) { ?>
                                        <li>
                                            <a href="/user/list.php"><?=cms('Main Nav: Users', 0, 'Users')?></a>
                                        </li>
                                    <? } ?>
                                    <? if (canDo('list', 'role')) { ?>
                                        <li>
                                            <a href="/role/list.php"><?=cms('Main Nav: Roles', 0, 'User Roles')?></a>
                                        </li>
                                    <? } ?>
                                    <? if(canDo('edit', 'recordTypeId')) { ?>
                                        <li>
                                            <a href="/label/print.php?mode=preview"><?=cms('Main Nav: Generate Labels', 0, 'Generate Labels')?></a>
                                        </li>
                                    <? } ?>
                                    <? if (canDo('list', 'cms')) { ?>
                                        <li>
                                            <a href="/cms/list.php"><?=cms('Main Nav: CMS', 0, 'CMS')?></a>
                                        </li>
                                    <? } ?>
                                    <? if (canDo('list', 'email')) { ?>
                                        <li>
                                            <a href="/email/list.php"><?=cms('Main Nav: Email Log', 0, 'Email Log')?></a>
                                        </li>
                                    <? } ?>
                                    <? if (canDo('list', 'configuration')) { ?>
                                        <li>
                                            <a href="/configuration/list.php"><?=cms('Main Nav: Site Settings', 0, 'Site Settings')?></a>
                                        </li>
                                    <? } ?>
                                </ul>
                            </li>
                        <? } ?>
                    </ul>
                </nav>
            </div>
        <? } ?>
        <div class="subheading"></div>
    </header>
    <?
    if($USER_ID) {
        displayUserNotices();
    }
    ?>

    <main>
        <div class="container body-pad">
<? });
