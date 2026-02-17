<?php
/**
 * RAG content index — chunk and store post content for retrieval.
 *
 * Indexes posts/pages on publish; provides get_relevant_chunks() for
 * RAG chat and internal-linking. No embeddings in core; filter
 * wp_pinch_rag_retrieve_chunks can add semantic search.
 *
 * @package WP_Pinch
 */

namespace WP_Pinch;

defined( 'ABSPATH' ) || exit;

/**
 * RAG content chunk index.
 */
class RAG_Index {

	/**
	 * Chunk size in characters (target ~1000).
	 */
	const CHUNK_SIZE = 1000;

	/**
	 * Overlap between chunks (chars) for context continuity.
	 */
	const CHUNK_OVERLAP = 100;

	/**
	 * Post types to index.
	 *
	 * @var string[]
	 */
	const POST_TYPES = array( 'post', 'page' );

	/**
	 * Wire hooks.
	 */
	public static function init(): void {
		if ( ! Feature_Flags::is_enabled( 'rag_indexing' ) ) {
			return;
		}
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 3 );
		add_action( 'delete_post', array( __CLASS__, 'on_delete_post' ), 10, 2 );
	}

	/**
	 * Table name for content chunks.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'wp_pinch_content_chunks';
	}

	/**
	 * Create the chunks table.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL DEFAULT 'post',
			chunk_index smallint(5) unsigned NOT NULL DEFAULT 0,
			content text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY post_type (post_type)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * On save_post: re-index published posts.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function on_save_post( int $post_id, \WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			self::delete_chunks_for_post( $post_id );
			return;
		}

		self::index_post( $post_id );
	}

	/**
	 * On delete_post: remove chunks.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function on_delete_post( int $post_id, \WP_Post $post ): void {
		self::delete_chunks_for_post( $post_id );
	}

	/**
	 * Delete all chunks for a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_chunks_for_post( int $post_id ): void {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE post_id = %d',
				$table,
				$post_id
			)
		);
	}

	/**
	 * Chunk post content and store in index.
	 *
	 * @param int $post_id Post ID.
	 * @return int Number of chunks stored.
	 */
	public static function index_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return 0;
		}

		self::delete_chunks_for_post( $post_id );

		$text = wp_strip_all_tags( $post->post_content );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );
		if ( '' === $text ) {
			return 0;
		}

		$chunks = self::chunk_text( $text );
		if ( empty( $chunks ) ) {
			return 0;
		}

		global $wpdb;
		$table = self::table_name();
		$count = 0;
		foreach ( $chunks as $i => $content ) {
			$result = $wpdb->insert(
				$table,
				array(
					'post_id'     => $post_id,
					'post_type'   => $post->post_type,
					'chunk_index' => $i,
					'content'     => $content,
				),
				array( '%d', '%s', '%d', '%s' )
			);
			if ( $result ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Split text into overlapping chunks.
	 *
	 * @param string $text Full text.
	 * @return string[]
	 */
	private static function chunk_text( string $text ): array {
		$len     = mb_strlen( $text );
		$chunks  = array();
		$start   = 0;
		$size    = (int) apply_filters( 'wp_pinch_rag_chunk_size', self::CHUNK_SIZE );
		$overlap = (int) apply_filters( 'wp_pinch_rag_chunk_overlap', self::CHUNK_OVERLAP );

		while ( $start < $len ) {
			$chunk = mb_substr( $text, $start, $size );
			$chunk = trim( $chunk );
			if ( '' !== $chunk ) {
				$chunks[] = $chunk;
			}
			$start += $size - $overlap;
		}

		return $chunks;
	}

	/**
	 * Retrieve chunks relevant to a query (keyword search).
	 *
	 * Can be overridden by filter wp_pinch_rag_retrieve_chunks for
	 * embedding-based retrieval.
	 *
	 * @param string   $query  Search query.
	 * @param int      $limit  Max chunks to return. Default 8.
	 * @param string[] $types  Post types to search. Default post, page.
	 * @return array<int, array{post_id: int, post_type: string, chunk_index: int, content: string, title: string}>
	 */
	public static function get_relevant_chunks( string $query, int $limit = 8, array $types = array() ): array {
		$limit = max( 1, min( 20, $limit ) );
		$types = empty( $types ) ? self::POST_TYPES : array_intersect( $types, self::POST_TYPES );

		/**
		 * Filter: custom retrieval (e.g. embedding similarity).
		 *
		 * @param array|null $chunks Chunks array or null to use default keyword search.
		 * @param string     $query  Query.
		 * @param int        $limit  Limit.
		 * @param string[]   $types  Post types.
		 */
		$custom = apply_filters( 'wp_pinch_rag_retrieve_chunks', null, $query, $limit, $types );
		if ( is_array( $custom ) ) {
			return array_slice( $custom, 0, $limit );
		}

		global $wpdb;
		$table        = self::table_name();
		$like         = '%' . $wpdb->esc_like( $query ) . '%';
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args         = array_merge( $types, array( $like, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $prepared is the return value of $wpdb->prepare().
		$prepared = $wpdb->prepare(
			"SELECT c.id, c.post_id, c.post_type, c.chunk_index, c.content FROM `{$table}` c " .
			"WHERE c.post_type IN ($placeholders) AND c.content LIKE %s " .
			'ORDER BY c.post_id, c.chunk_index LIMIT %d',
			$args
		);
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['post_id'];
			$title   = get_the_title( $post_id );
			$out[]   = array(
				'post_id'     => $post_id,
				'post_type'   => $row['post_type'],
				'chunk_index' => (int) $row['chunk_index'],
				'content'     => $row['content'],
				'title'       => ( '' !== $title ) ? $title : '',
			);
		}
		return $out;
	}

	/**
	 * Whether RAG indexing is enabled and table exists.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		if ( ! Feature_Flags::is_enabled( 'rag_indexing' ) ) {
			return false;
		}
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
	}
}
