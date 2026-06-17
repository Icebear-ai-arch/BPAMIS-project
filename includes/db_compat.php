<?php
/**
 * BPAMIS DB compatibility helpers.
 *
 * Purpose:
 * - Work on shared hosts that lack mysqlnd (mysqli_stmt::get_result may be unavailable or return false)
 * - Work on Linux hosts with case-sensitive table names by resolving actual table names
 */

// Some shared hosts disable mbstring; polyfill the small subset we use to avoid fatal "undefined function".
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null)
    {
        return strlen((string)$string);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        $string = (string)$string;
        $start = (int)$start;
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, (int)$length);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null)
    {
        return strtolower((string)$string);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null)
    {
        return strtoupper((string)$string);
    }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null)
    {
        return strpos((string)$haystack, (string)$needle, (int)$offset);
    }
}
if (!function_exists('mb_strrpos')) {
    function mb_strrpos($haystack, $needle, $offset = 0, $encoding = null)
    {
        // Basic fallback; ignores encoding and negative offsets.
        return strrpos((string)$haystack, (string)$needle, (int)$offset);
    }
}
if (!function_exists('mb_detect_encoding')) {
    function mb_detect_encoding($string, $enc = null, $strict = false)
    {
        return 'UTF-8';
    }
}
if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($string, $to_encoding, $from_encoding = null)
    {
        return (string)$string;
    }
}

if (!function_exists('bpamis_stmt_fetch_all_assoc')) {
    function bpamis_stmt_fetch_all_assoc(mysqli_stmt $stmt): array
    {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res instanceof mysqli_result) {
                $rows = [];
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                $res->free();
                return $rows;
            }
        }

        $stmt->store_result();
        $meta = $stmt->result_metadata();
        if (!$meta) {
            return [];
        }

        $fields = [];
        $row = [];
        $bind = [];
        while ($field = $meta->fetch_field()) {
            $name = $field->name;
            $fields[] = $name;
            $row[$name] = null;
            $bind[] = &$row[$name];
        }

        if (!empty($bind)) {
            call_user_func_array([$stmt, 'bind_result'], $bind);
        }

        $rows = [];
        while ($stmt->fetch()) {
            $copy = [];
            foreach ($fields as $name) {
                $copy[$name] = $row[$name];
            }
            $rows[] = $copy;
        }
        $meta->free();
        return $rows;
    }
}

if (!function_exists('bpamis_query_first_assoc')) {
    /** @return array<string, mixed>|null */
    function bpamis_query_first_assoc(mysqli $conn, string $sql)
    {
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            return null;
        }
        $row = $res->fetch_assoc();
        $res->free();
        return $row ?: null;
    }
}

if (!function_exists('bpamis_query_scalar')) {
    /**
     * @param string|null $key If null, returns the first column in the first row.
     * @return mixed
     */
    function bpamis_query_scalar(mysqli $conn, string $sql, ?string $key = null, $default = null)
    {
        $row = bpamis_query_first_assoc($conn, $sql);
        if ($row === null) {
            return $default;
        }
        if ($key === null) {
            $first = reset($row);
            return $first === false ? $default : $first;
        }
        return array_key_exists($key, $row) ? $row[$key] : $default;
    }
}

if (!class_exists('BpamisArrayResult')) {
    class BpamisArrayResult
    {
        /** @var array<int, array<string, mixed>> */
        private $rows;
        private $pos = 0;
        public $num_rows;

        /** @param array<int, array<string, mixed>> $rows */
        public function __construct(array $rows)
        {
            $this->rows = array_values($rows);
            $this->num_rows = count($this->rows);
        }

        /** @return array<string, mixed>|null */
        public function fetch_assoc()
        {
            if ($this->pos >= $this->num_rows) {
                return null;
            }
            return $this->rows[$this->pos++];
        }

        /** @return array<int, mixed>|null */
        public function fetch_row()
        {
            $row = $this->fetch_assoc();
            if ($row === null) return null;
            return array_values($row);
        }

        /**
         * @return array<int, array<string, mixed>>|array<int, array<int, mixed>>
         */
        public function fetch_all($mode = MYSQLI_ASSOC)
        {
            $remaining = array_slice($this->rows, $this->pos);
            $this->pos = $this->num_rows;

            if ($mode === MYSQLI_ASSOC) {
                return $remaining;
            }

            $out = [];
            foreach ($remaining as $r) {
                $out[] = array_values($r);
            }
            return $out;
        }

        public function data_seek($offset)
        {
            if ($offset < 0) $offset = 0;
            if ($offset > $this->num_rows) $offset = $this->num_rows;
            $this->pos = $offset;
            return true;
        }

        public function free()
        {
            // no-op for array-backed results
        }

        public function close()
        {
            // no-op for array-backed results
        }
    }
}

if (!function_exists('bpamis_stmt_get_result')) {
    /**
     * Compatibility wrapper for $stmt->get_result() when mysqlnd is missing.
     *
     * @return mysqli_result|BpamisArrayResult
     */
    function bpamis_stmt_get_result(mysqli_stmt $stmt)
    {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            if ($res instanceof mysqli_result) {
                return $res;
            }
        }
        return new BpamisArrayResult(bpamis_stmt_fetch_all_assoc($stmt));
    }
}

if (!function_exists('bpamis_conn_cache_key')) {
    function bpamis_conn_cache_key(mysqli $conn): string
    {
        if (function_exists('spl_object_id')) {
            return (string)spl_object_id($conn);
        }
        return spl_object_hash($conn);
    }
}

if (!function_exists('bpamis_table')) {
    function bpamis_table(mysqli $conn, string $desired): string
    {
        static $tablesByConn = [];

        $key = bpamis_conn_cache_key($conn);
        if (!array_key_exists($key, $tablesByConn)) {
            $tablesByConn[$key] = [];
            $res = $conn->query('SHOW TABLES');
            if ($res) {
                while ($row = $res->fetch_row()) {
                    $tbl = (string)$row[0];
                    $tablesByConn[$key][strtolower($tbl)] = $tbl;
                }
                $res->free();
            }
        }

        $lower = strtolower($desired);
        return $tablesByConn[$key][$lower] ?? $desired;
    }
}

if (!function_exists('bpamis_quote_ident')) {
    function bpamis_quote_ident(string $ident): string
    {
        return '`' . str_replace('`', '``', $ident) . '`';
    }
}

if (!function_exists('bpamis_quote_table')) {
    function bpamis_quote_table(string $table): string
    {
        return bpamis_quote_ident($table);
    }
}

if (!function_exists('bpamis_table_has_column')) {
    function bpamis_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        $tableQ = bpamis_quote_table($table);
        $colEsc = $conn->real_escape_string($column);
        $q = $conn->query("SHOW COLUMNS FROM {$tableQ} LIKE '{$colEsc}'");
        if (!$q) return false;
        $ok = $q->num_rows > 0;
        $q->close();
        return $ok;
    }
}

if (!function_exists('bpamis_first_existing_column')) {
    function bpamis_first_existing_column(mysqli $conn, string $table, array $candidates)
    {
        foreach ($candidates as $col) {
            if ($col !== '' && bpamis_table_has_column($conn, $table, $col)) {
                return $col;
            }
        }
        return null;
    }
}
