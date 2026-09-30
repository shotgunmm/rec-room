<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Form_builder_upd
{
    public $version = '1.2.1';

    public function __construct()
    {
        ee()->load->dbforge();
    }

    public function install()
    {
        // Register module
        ee()->db->insert('modules', array(
            'module_name'        => 'Form_builder',
            'module_version'     => $this->version,
            'has_cp_backend'     => 'y',
            'has_publish_fields' => 'n'
        ));

        // Create forms table
        ee()->dbforge->add_field(array(
            'form_id' => array(
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true
            ),
            'site_id' => array(
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 1
            ),
            'form_name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255
            ),
            'form_label' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255
            ),
            'recipient_email' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'reply_to_field' => array(
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true
            ),
            'email_subject' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'success_redirect' => array(
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true
            ),
            'send_confirmation' => array(
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'n'
            ),
            'confirmation_template' => array(
                'type' => 'TEXT',
                'null' => true
            ),
            'confirmation_subject' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'confirmation_from_name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'confirmation_from_email' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'is_active' => array(
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'y'
            ),
            'created_at' => array(
                'type' => 'DATETIME',
                'null' => true
            ),
            'updated_at' => array(
                'type' => 'DATETIME',
                'null' => true
            ),
            'mailchimp_success_text' => array(
                'type' => 'TEXT',
                'null' => true
            ),
            'mailchimp_failure_text' => array(
                'type' => 'TEXT',
                'null' => true
            )
        ));
        ee()->dbforge->add_key('form_id', true);
        ee()->dbforge->add_key('site_id');
        ee()->dbforge->add_key('form_name');
        ee()->dbforge->create_table('form_builder_forms');

        // Create fields table
        ee()->dbforge->add_field(array(
            'field_id' => array(
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true
            ),
            'form_id' => array(
                'type'     => 'INT',
                'unsigned' => true
            ),
            'field_name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 100
            ),
            'field_label' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255
            ),
            'field_type' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50
            ),
            'field_options' => array(
                'type' => 'TEXT',
                'null' => true
            ),
            'placeholder' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'default_value' => array(
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true
            ),
            'is_required' => array(
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'n'
            ),
            'field_header' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'confirm' => array(
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'n'
            ),
            'validation_rules' => array(
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true
            ),
            'field_order' => array(
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0
            ),
            'css_class' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'file_types' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true
            ),
            'max_file_size' => array(
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true
            ),
            'field_config' => array(
                'type' => 'TEXT',
                'null' => true
            )
        ));
        ee()->dbforge->add_key('field_id', true);
        ee()->dbforge->add_key('form_id');
        ee()->dbforge->add_key('field_order');
        ee()->dbforge->create_table('form_builder_fields');

        // Create submissions table
        ee()->dbforge->add_field(array(
            'submission_id' => array(
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true
            ),
            'form_id' => array(
                'type'     => 'INT',
                'unsigned' => true
            ),
            'site_id' => array(
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 1
            ),
            'submission_data' => array(
                'type' => 'LONGTEXT',
                'null' => true
            ),
            'ip_address' => array(
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true
            ),
            'user_agent' => array(
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true
            ),
            'status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'new'
            ),
            'is_spam' => array(
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'n'
            ),
            'email_sent' => array(
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'n'
            ),
            'confirmation_sent' => array(
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'n'
            ),
            'mailchimp_status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'none'
            ),
            'mailchimp_error' => array(
                'type' => 'TEXT',
                'null' => true
            ),
            'submitted_at' => array(
                'type' => 'DATETIME',
                'null' => true
            )
        ));
        ee()->dbforge->add_key('submission_id', true);
        ee()->dbforge->add_key('form_id');
        ee()->dbforge->add_key('site_id');
        ee()->dbforge->add_key('status');
        ee()->dbforge->add_key('submitted_at');
        ee()->dbforge->create_table('form_builder_submissions');
        ee()->db->query('ALTER TABLE exp_form_builder_submissions ADD INDEX idx_mailchimp_status (mailchimp_status)');

        // Create settings table for SMTP and global settings
        ee()->dbforge->add_field(array(
            'setting_id' => array(
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true
            ),
            'site_id' => array(
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 1
            ),
            'setting_key' => array(
                'type'       => 'VARCHAR',
                'constraint' => 100
            ),
            'setting_value' => array(
                'type' => 'TEXT',
                'null' => true
            )
        ));
        ee()->dbforge->add_key('setting_id', true);
        ee()->dbforge->add_key('site_id');
        ee()->dbforge->add_key('setting_key');
        ee()->dbforge->create_table('form_builder_settings');

        // Create mailchimp lists cache table
        ee()->dbforge->add_field(array(
            'list_id' => array(
                'type'       => 'VARCHAR',
                'constraint' => 32
            ),
            'site_id' => array(
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 1
            ),
            'list_name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255
            ),
            'member_count' => array(
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0
            ),
            'cached_at' => array(
                'type' => 'DATETIME',
                'null' => true
            )
        ));
        ee()->dbforge->add_key('list_id', true);
        ee()->dbforge->add_key('site_id');
        ee()->dbforge->create_table('form_builder_mailchimp_lists');

        // Create mailchimp merge tags cache table
        ee()->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'unsigned' => true, 'auto_increment' => true),
            'list_id' => array('type' => 'VARCHAR', 'constraint' => 32),
            'site_id' => array('type' => 'INT', 'unsigned' => true, 'default' => 1),
            'tag' => array('type' => 'VARCHAR', 'constraint' => 32),
            'name' => array('type' => 'VARCHAR', 'constraint' => 255),
            'cached_at' => array('type' => 'DATETIME', 'null' => true)
        ));
        ee()->dbforge->add_key('id', true);
        ee()->dbforge->add_key('site_id');
        ee()->dbforge->create_table('form_builder_mailchimp_merge_tags');

        // Create mailchimp subscriber tags cache table
        ee()->dbforge->add_field(array(
            'id' => array('type' => 'INT', 'unsigned' => true, 'auto_increment' => true),
            'list_id' => array('type' => 'VARCHAR', 'constraint' => 32),
            'site_id' => array('type' => 'INT', 'unsigned' => true, 'default' => 1),
            'name' => array('type' => 'VARCHAR', 'constraint' => 255),
            'cached_at' => array('type' => 'DATETIME', 'null' => true)
        ));
        ee()->dbforge->add_key('id', true);
        ee()->dbforge->add_key('site_id');
        ee()->dbforge->create_table('form_builder_mailchimp_sub_tags');

        // Insert default settings
        $this->insertDefaultSettings();

        // Register action for form submission
        ee()->db->insert('actions', array(
            'class'  => 'Form_builder',
            'method' => 'submit'
        ));

        return true;
    }

    private function insertDefaultSettings()
    {
        $site_id = ee()->config->item('site_id');
        $defaults = array(
            'smtp_enabled'           => 'n',
            'smtp_host'              => '',
            'smtp_port'              => '587',
            'smtp_username'          => '',
            'smtp_password'          => '',
            'smtp_encryption'        => 'tls',
            'from_name'              => '',
            'from_email'             => '',
            'mailchimp_api_key'      => '',
            'mailchimp_alerts_email' => ''
        );

        foreach ($defaults as $key => $value) {
            ee()->db->insert('form_builder_settings', array(
                'site_id'       => $site_id,
                'setting_key'   => $key,
                'setting_value' => $value
            ));
        }
    }

    public function uninstall()
    {
        // Remove module
        ee()->db->where('module_name', 'Form_builder')->delete('modules');

        // Remove action
        ee()->db->where('class', 'Form_builder')->delete('actions');

        // Drop tables
        ee()->dbforge->drop_table('form_builder_forms');
        ee()->dbforge->drop_table('form_builder_fields');
        ee()->dbforge->drop_table('form_builder_submissions');
        ee()->dbforge->drop_table('form_builder_settings');
        ee()->dbforge->drop_table('form_builder_mailchimp_lists');
        ee()->dbforge->drop_table('form_builder_mailchimp_merge_tags');
        ee()->dbforge->drop_table('form_builder_mailchimp_sub_tags');

        return true;
    }

    public function update($current = '')
    {
        if (version_compare($current, $this->version, '=')) {
            return false;
        }

        // Add index for submitted_at if it doesn't exist
        if (version_compare($current, '1.0.0', '<')) {
            $idx = ee()->db->query("SHOW INDEX FROM exp_form_builder_submissions WHERE Key_name = 'idx_submitted_at'")->num_rows();
            if ($idx === 0) {
                ee()->db->query('ALTER TABLE exp_form_builder_submissions ADD INDEX idx_submitted_at (submitted_at)');
            }
        }

        if (version_compare($current, '1.0.1', '<')) {
            // Add field_header column if missing
            $fields = ee()->db->query("SHOW COLUMNS FROM exp_form_builder_fields LIKE 'field_header'")->num_rows();
            if ($fields === 0) {
                ee()->dbforge->add_column('form_builder_fields', array(
                    'field_header' => array(
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true
                    )
                ));
            }

            // Add confirm column if missing
            $cols = ee()->db->query("SHOW COLUMNS FROM exp_form_builder_fields LIKE 'confirm'")->num_rows();
            if ($cols === 0) {
                ee()->dbforge->add_column('form_builder_fields', array(
                    'confirm' => array(
                        'type'       => 'CHAR',
                        'constraint' => 1,
                        'default'    => 'n'
                    )
                ));
            }
        }

        if (version_compare($current, '1.0.3', '<')) {
            $col = ee()->db->query("SHOW COLUMNS FROM exp_form_builder_fields LIKE 'is_header'")->num_rows();
            if ($col > 0) {
                ee()->dbforge->drop_column('form_builder_fields', 'is_header');
            }
        }

        if (version_compare($current, '1.0.4', '<')) {
            ee()->db->query("UPDATE exp_form_builder_fields SET placeholder   = '' WHERE placeholder   IS NULL");
            ee()->db->query("UPDATE exp_form_builder_fields SET default_value = '' WHERE default_value IS NULL");
            ee()->db->query("UPDATE exp_form_builder_fields SET css_class     = '' WHERE css_class     IS NULL");
        }

        if (version_compare($current, '1.1.0', '<')) {
            // Add field_config column to fields table
            $col = ee()->db->query("SHOW COLUMNS FROM exp_form_builder_fields LIKE 'field_config'")->num_rows();
            if ($col === 0) {
                ee()->dbforge->add_column('form_builder_fields', array(
                    'field_config' => array(
                        'type' => 'TEXT',
                        'null' => true
                    )
                ));
            }

            // Add mailchimp_status column to submissions table
            $col = ee()->db->query("SHOW COLUMNS FROM exp_form_builder_submissions LIKE 'mailchimp_status'")->num_rows();
            if ($col === 0) {
                ee()->dbforge->add_column('form_builder_submissions', array(
                    'mailchimp_status' => array(
                        'type'       => 'VARCHAR',
                        'constraint' => 32,
                        'default'    => 'none'
                    )
                ));
                $idx = ee()->db->query("SHOW INDEX FROM exp_form_builder_submissions WHERE Key_name = 'idx_mailchimp_status'")->num_rows();
                if ($idx === 0) {
                    ee()->db->query('ALTER TABLE exp_form_builder_submissions ADD INDEX idx_mailchimp_status (mailchimp_status)');
                }
            }

            // Add per-form Mailchimp success/failure text columns
            $col = ee()->db->query("SHOW COLUMNS FROM exp_form_builder_forms LIKE 'mailchimp_success_text'")->num_rows();
            if ($col === 0) {
                ee()->dbforge->add_column('form_builder_forms', array(
                    'mailchimp_success_text' => array(
                        'type' => 'TEXT',
                        'null' => true
                    ),
                    'mailchimp_failure_text' => array(
                        'type' => 'TEXT',
                        'null' => true
                    )
                ));
            }

            // Create mailchimp_lists cache table
            if (!ee()->db->table_exists('form_builder_mailchimp_lists')) {
                ee()->dbforge->add_field(array(
                    'list_id' => array(
                        'type'       => 'VARCHAR',
                        'constraint' => 32
                    ),
                    'site_id' => array(
                        'type'     => 'INT',
                        'unsigned' => true,
                        'default'  => 1
                    ),
                    'list_name' => array(
                        'type'       => 'VARCHAR',
                        'constraint' => 255
                    ),
                    'member_count' => array(
                        'type'     => 'INT',
                        'unsigned' => true,
                        'default'  => 0
                    ),
                    'cached_at' => array(
                        'type' => 'DATETIME',
                        'null' => true
                    )
                ));
                ee()->dbforge->add_key('list_id', true);
                ee()->dbforge->add_key('site_id');
                ee()->dbforge->create_table('form_builder_mailchimp_lists');
            }

            // Insert default Mailchimp settings if they don't exist
            $site_id = ee()->config->item('site_id');
            $mc_defaults = array(
                'mailchimp_api_key'      => '',
                'mailchimp_alerts_email' => ''
            );
            foreach ($mc_defaults as $key => $value) {
                $exists = ee()->db->where('site_id', $site_id)
                    ->where('setting_key', $key)
                    ->count_all_results('form_builder_settings');
                if ($exists === 0) {
                    ee()->db->insert('form_builder_settings', array(
                        'site_id'       => $site_id,
                        'setting_key'   => $key,
                        'setting_value' => $value
                    ));
                }
            }
        }

        if (version_compare($current, '1.1.1', '<')) {
            if (!ee()->db->table_exists('form_builder_mailchimp_merge_tags')) {
                ee()->dbforge->add_field(array(
                    'id' => array('type' => 'INT', 'unsigned' => true, 'auto_increment' => true),
                    'list_id' => array('type' => 'VARCHAR', 'constraint' => 32),
                    'site_id' => array('type' => 'INT', 'unsigned' => true, 'default' => 1),
                    'tag' => array('type' => 'VARCHAR', 'constraint' => 32),
                    'name' => array('type' => 'VARCHAR', 'constraint' => 255),
                    'cached_at' => array('type' => 'DATETIME', 'null' => true)
                ));
                ee()->dbforge->add_key('id', true);
                ee()->dbforge->add_key('site_id');
                ee()->dbforge->create_table('form_builder_mailchimp_merge_tags');
            }
        }

        if (version_compare($current, '1.1.2', '<')) {
            if (!ee()->db->table_exists('form_builder_mailchimp_sub_tags')) {
                ee()->dbforge->add_field(array(
                    'id' => array('type' => 'INT', 'unsigned' => true, 'auto_increment' => true),
                    'list_id' => array('type' => 'VARCHAR', 'constraint' => 32),
                    'site_id' => array('type' => 'INT', 'unsigned' => true, 'default' => 1),
                    'name' => array('type' => 'VARCHAR', 'constraint' => 255),
                    'cached_at' => array('type' => 'DATETIME', 'null' => true)
                ));
                ee()->dbforge->add_key('id', true);
                ee()->dbforge->add_key('site_id');
                ee()->dbforge->create_table('form_builder_mailchimp_sub_tags');
            }
        }

        if (version_compare($current, '1.1.3', '<')) {
            $col = ee()->db->query("SHOW COLUMNS FROM exp_form_builder_submissions LIKE 'mailchimp_error'")->num_rows();
            if ($col === 0) {
                ee()->dbforge->add_column('form_builder_submissions', array(
                    'mailchimp_error' => array('type' => 'TEXT', 'null' => true)
                ));
            }
        }

        return true;
    }
}
