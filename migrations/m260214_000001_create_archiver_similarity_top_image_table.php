<?php

use yii\db\Migration;

class m260214_000001_create_archiver_similarity_top_image_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%archiver_similarity_top_image}}', [
            'id' => $this->primaryKey(),
            'canonical_image_key' => $this->string(512)->notNull(),
            'occurrence_count' => $this->integer()->notNull()->defaultValue(0),
            'groups_count' => $this->integer()->notNull()->defaultValue(0),
            'vm_occurrence_count' => $this->integer()->notNull()->defaultValue(0),
            'last_seen_at' => $this->integer()->null(),
            'avg_similarity' => $this->decimal(10, 6)->null(),
            'recalculated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx-archiver_similarity_top_image-canonical', '{{%archiver_similarity_top_image}}', 'canonical_image_key', true);
        $this->createIndex('idx-archiver_similarity_top_image-occurrence', '{{%archiver_similarity_top_image}}', 'occurrence_count');
        $this->createIndex('idx-archiver_similarity_top_image-last_seen', '{{%archiver_similarity_top_image}}', 'last_seen_at');
    }

    public function safeDown()
    {
        $this->dropTable('{{%archiver_similarity_top_image}}');
    }
}
