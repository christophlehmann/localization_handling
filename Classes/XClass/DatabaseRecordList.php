<?php

namespace ChristophLehmann\LocalizationHandling\XClass;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * XClass may hide localization buttons in list view depending on TsConfig settings of mod.web_layout.localization
 *
 * See also: https://docs.typo3.org/m/typo3/reference-tsconfig/master/en-us/PageTsconfig/Mod.html#localization-enablecopy
 */
class DatabaseRecordList extends \TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList
{

    /**
     * @var array
     */
    protected $languageTranslationModeMap;

    /**
     * @var array
     */
    protected $copiedElementsMap;

    /**
     * Creates the localization panel
     *
     * @param string $table The table
     * @param mixed[] $row The record for which to make the localization panel.
     * @return string[] Array with key 0/1 with content for column 1 and 2
     */
    public function makeLocalizationPanel($table, $row)
    {

// Patch start
        if ($table === 'tt_content') {
            if (!is_array($this->languageTranslationModeMap)) {
                $this->createLanguageTranslationModeMap();
            }
            if (!is_array($this->copiedElementsMap)) {
                $this->fillCopiedElementsMap();
            }
        }
// Patch end

        $out = [
            0 => '',
            1 => ''
        ];
        // Reset translations
        $this->translations = [];

        // Language title and icon:
        $out[0] = $this->languageFlag($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]);
        // Guard clause so we can quickly return if a record is localized to "all languages"
        // It should only be possible to localize a record off default (uid 0)
        // Reasoning: The Parent is for ALL languages... why overlay with a localization?
        if ((int)$row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] === -1) {
            return $out;
        }
        $translations = $this->translateTools->translationInfo($table, $row['uid'], 0, $row, $this->selFieldList);
        if (is_array($translations)) {
            $this->translations = $translations['translations'];
            // Traverse page translations and add icon for each language that does NOT yet exist and is included in site configuration:
            $lNew = '';
            foreach ($this->pageOverlays as $lUid_OnPage => $lsysRec) {

// Patch Start - do not show translation flag if element was already copied
                if (in_array($translations['uid'], $this->copiedElementsMap[$lUid_OnPage])) {
                    continue;
                }
// Patch end
                if (isset($this->systemLanguagesOnPage[$lUid_OnPage])
                    && $this->isEditable($table)
                    && !$this->isRecordDeletePlaceholder($row)
                    && !isset($translations['translations'][$lUid_OnPage])
                    && $this->getBackendUserAuthentication()->checkLanguageAccess($lUid_OnPage)
                ) {
                    $redirectUrl = (string)$this->uriBuilder->buildUriFromRoute(
                        'record_edit',
                        [
                            'justLocalized' => $table . ':' . $row['uid'] . ':' . $lUid_OnPage,
                            'returnUrl' => $this->listURL()
                        ]
                    );
                    $href = BackendUtility::getLinkToDataHandlerAction(
                        '&cmd[' . $table . '][' . $row['uid'] . '][localize]=' . $lUid_OnPage,
                        $redirectUrl
                    );
                    $language = BackendUtility::getRecord('sys_language', $lUid_OnPage, 'title');
                    if ($this->languageIconTitles[$lUid_OnPage]['flagIcon']) {
                        $lC = $this->iconFactory->getIcon($this->languageIconTitles[$lUid_OnPage]['flagIcon'],
                            Icon::SIZE_SMALL)->render();
                    } else {
                        $lC = $this->languageIconTitles[$lUid_OnPage]['title'];
                    }

// Patch begin
                    if ($table === 'tt_content') {
                        $lC = '<a href="#" 
                                    class="btn btn-default btn-sm t3js-localize"
                                    title="Translate/Copy" 
                                    data-page="' . htmlentities($this->pageRow['title']) . '"
                                    data-has-elements="0"
                                    data-allow-copy="' . (int)$this->languageTranslationModeMap[$lUid_OnPage]['copyEnabled'] . '" 
                                    data-allow-translate="' . (int)$this->languageTranslationModeMap[$lUid_OnPage]['localizationEnabled'] . '" 
                                    data-table="tt_content"
                                    data-page-id="' . $row['pid'] . '" 
                                    data-language-id="' . $lUid_OnPage . '" 
                                    data-language-name="' . $language['title'] . '">
                                        <span class="t3js-icon icon icon-size-small icon-state-default icon-actions-localize" 
                                            data-identifier="actions-localize">
	                                    <span class="icon-markup">' . $lC . '
	                                </span>
                               </span> </a>';
                    } else {
// Old logic
                        $lC = '<a href="' . htmlspecialchars($href) . '" title="'
                            . htmlspecialchars($language['title']) . '" class="btn btn-default t3js-action-localize">'
                            . $lC . '</a> ';
                    }
// Patch end
                    $lNew .= $lC;
                }
            }
            if ($lNew) {
                $out[1] .= $lNew;
            }
        } elseif ($row['l18n_parent']) {
            $out[0] = '&nbsp;&nbsp;&nbsp;&nbsp;' . $out[0];
        }
        return $out;
    }

    protected function createLanguageTranslationModeMap()
    {
        $localizationConfiguration = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_layout.']['localization.'] ?? [];
        $copyEnabled = !isset($localizationConfiguration['enableCopy']) || (isset($localizationConfiguration['enableCopy']) && (bool)$localizationConfiguration['enableCopy'] === true);
        $localizationEnabled = !isset($localizationConfiguration['enableTranslate']) || (isset($localizationConfiguration['enableTranslate']) && (bool)$localizationConfiguration['enableTranslate'] === true);

        $this->languageTranslationModeMap = [];
        foreach ($this->pageOverlays as $pageOverlay) {
            $this->languageTranslationModeMap[$pageOverlay['sys_language_uid']] = [
                'copyEnabled' => $copyEnabled,
                'localizationEnabled' => $localizationEnabled,
            ];
        }
        $table = 'tt_content';
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $languageOverlayRecords = $queryBuilder
            ->select('sys_language_uid', 'l18n_parent', 'l10n_source')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->pageRow['uid'], \PDO::PARAM_INT)),
                $queryBuilder->expr()->in('sys_language_uid', implode(',', array_keys($this->languageTranslationModeMap))),
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->gt('l18n_parent', 0),
                    $queryBuilder->expr()->gt('l10n_source', 0)
                )
            )
            ->execute()
            ->fetchAll();

        foreach ($languageOverlayRecords as $overlayRecord) {
            if ($copyEnabled && $overlayRecord['l10n_source'] > 0 && $overlayRecord['l18n_parent'] == 0) {
                // Overlay language is in copy mode
                $this->languageTranslationModeMap[$overlayRecord['sys_language_uid']]['copyEnabled'] = true;
                $this->languageTranslationModeMap[$overlayRecord['sys_language_uid']]['localizationEnabled'] = false;
            } elseif($localizationEnabled && $overlayRecord['l18n_parent'] > 0) {
                // Overlay language is in translation mode
                $this->languageTranslationModeMap[$overlayRecord['sys_language_uid']]['localizationEnabled'] = true;
                $this->languageTranslationModeMap[$overlayRecord['sys_language_uid']]['copyEnabled'] = false;
            }
        }
    }

    protected function fillCopiedElementsMap() {
        $table = 'tt_content';
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('uid', 'l10n_source', 'sys_language_uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->pageRow['uid'], \PDO::PARAM_INT)),
                $queryBuilder->expr()->gt('sys_language_uid', 0),
                $queryBuilder->expr()->eq('l18n_parent', 0)
            )
            ->execute()
            ->fetchAll();

        foreach($rows as $row) {
            $this->copiedElementsMap[$row['sys_language_uid']][] = $row['l10n_source'];
        }
    }
}