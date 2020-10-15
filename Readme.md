### Draft: Improved localization handling in TYPO3 list view in terms of copy/translation mode

According to this recordlist example

![Recordlist](/Documentation/Images/tt_content_recordlist.png)

* A "Localization flag" is shown when
  * There is no copy OR translation of the element in the target language

* A "Localization flag" triggers the localization dialog
  * It respects/unifies [PageLayoutView - Allow to disable copy- / translate- buttons](https://docs.typo3.org/c/typo3/cms-core/master/en-us/Changelog/9.0/Feature-76910-PageLayoutViewAllowToDisableCopyTranslateButtons.html)
  * It checks if target language is in copy or translation mode and disables copy/translation to prevent "mixed mode"

![LocalizationWizard](/Documentation/Images/localization_copy_wizard_in_listview.png)

#### Further possibilities which may lead to mixed content

In the "edit view" there is the language navigation. My idea ist to replace it with 

* "flag buttons" with an arrow to jump to the target language
* "flag buttons" with a + which creates a new translation

or simple adapt the links to trigger the localization wizard 

![LocalizationWizard](/Documentation/Images/language_switch_edit_view.png)
