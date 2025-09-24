<?php

namespace Sylphian\Library\Install;

use Sylphian\Library\Logger\Logger;
use XF\Entity\UserField;

trait SylInstallHelperTrait
{
    protected const array DEFAULT_USER_FIELD_OPTIONS = [
        'field_type'        => 'textbox',
        'display_order'     => 1,
        'display_group'     => 'personal',
        'required'          => false,
        'user_editable'     => 'yes',
        'moderator_editable'=> true,
        'show_registration' => false,
        'viewable_profile'  => true,
        'viewable_message'  => false,
        'max_length'        => 0,
    ];

	/**
	 * Create a custom user field
	 *
	 * @param string $fieldId Identifier for the field (must be unique)
	 * @param string $title Title phrase for the field
	 * @param string $description Description phrase for the field (optional)
	 * @param array $fieldOptions Additional field configuration options
	 *
	 * @return bool Success/failure
	 */
    public function createUserField(string $fieldId, string $title, string $description = '', array $fieldOptions = []): bool
    {
        $logger = Logger::withAddonId('Sylphian/Library');

        try {
            $logger->debug("Creating user field: {$fieldId}", [
                'title'   => $title,
                'options' => $fieldOptions
            ]);

            /** @var UserField $field */
            $field = \XF::em()->create('XF:UserField');
            $field->field_id = $fieldId;

            $options = array_merge(self::DEFAULT_USER_FIELD_OPTIONS, $fieldOptions);
            $logger->debug('Setting field options', ['merged_options' => $options]);
            $field->bulkSet($options);

            if (
                in_array($field->field_type, ['select', 'radio', 'checkbox', 'multiselect'], true) &&
                empty($field->field_choices)
            ) {
                $logger->error('Field choices required but not provided', [
                    'field_id'    => $fieldId,
                    'field_type'  => $field->field_type
                ]);
                return false;
            }

            if (!$field->save(false)) {
                $logger->error('Field save failed', [
                    'field_id' => $fieldId,
                    'errors'   => $field->getErrors()
                ]);
                return false;
            }

            $this->saveFieldPhrases($field, $title, $description, $logger);

            $logger->debug('Rebuilding field cache');
            \XF::repository('XF:UserField')->rebuildFieldCache();

            return true;
        } catch (\Exception $e) {
            $logger->error('Exception in createUserField', [
                'field_id'  => $fieldId,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    protected function saveFieldPhrases(UserField $field, string $title, string $description, $logger): void
    {
        // Title phrase
        $titlePhrase = $field->getMasterPhrase(true);
        $titlePhrase->phrase_text = $title;
        $titlePhrase->global_cache = true;
        $titlePhrase->save(false);

        // Description phrase
        if ($description !== '') {
            $descPhrase = $field->getMasterPhrase(false);
            $descPhrase->phrase_text = $description;
            $descPhrase->global_cache = true;
            $descPhrase->save(false);
        }

        $logger->debug('Field phrases saved successfully', [
            'field_id' => $field->field_id,
            'title'    => $title,
            'description' => $description
        ]);
    }

    public function removeUserField(string $fieldId): bool
    {
        try {
            /** @var UserField|null $field */
            $field = \XF::em()->find('XF:UserField', $fieldId);

            if (!$field) {
                return true;
            }

            $success = $field->delete(false);

            if ($success) {
                \XF::repository('XF:UserField')->rebuildFieldCache();
            }

            return $success;
        } catch (\Exception $e) {
            \XF::logException($e, false, 'Error removing user field: ');
            return false;
        }
    }
}
