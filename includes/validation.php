<?php
class Validator {
    private array $errors = [];

    public function required(string $field, string $value, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (empty(trim($value))) {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function email(string $field, string $value): self {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Invalid email address.";
        }
        return $this;
    }

    public function minLength(string $field, string $value, int $min, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (!empty($value) && strlen($value) < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, string $value, int $max, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (!empty($value) && strlen($value) > $max) {
            $this->errors[$field] = "{$label} must not exceed {$max} characters.";
        }
        return $this;
    }

    public function numeric(string $field, $value): self {
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = "Must be a valid number.";
        }
        return $this;
    }

    public function phone(string $field, string $value): self {
        if (!empty($value) && !preg_match('/^\+?[\d\s\-()]{7,20}$/', $value)) {
            $this->errors[$field] = "Invalid phone number format.";
        }
        return $this;
    }

    public function matches(string $field, string $value, string $matchValue, string $matchLabel = 'Confirmation'): self {
        if ($value !== $matchValue) {
            $this->errors[$field] = "Does not match {$matchLabel}.";
        }
        return $this;
    }

    public function unique(string $field, string $value, string $table, string $column, ?int $excludeId = null): self {
        if (empty($value)) return $this;
        $db = getDB();
        $sql = "SELECT id FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $this->errors[$field] = "This " . str_replace('_', ' ', $column) . " is already taken.";
        }
        return $this;
    }

    public function inList(string $field, $value, array $list): self {
        if (!empty($value) && !in_array($value, $list)) {
            $this->errors[$field] = "Invalid selection.";
        }
        return $this;
    }

    public function file(string $field, ?array $file, array $allowedExts = ['jpg','jpeg','png','pdf'], int $maxSize = 2097152): self {
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) {
                $this->errors[$field] = "File type not allowed. Allowed: " . implode(', ', $allowedExts);
            } elseif ($file['size'] > $maxSize) {
                $this->errors[$field] = "File size must not exceed " . ($maxSize / 1048576) . "MB.";
            }
        }
        return $this;
    }

    public function date(string $field, string $value): self {
        if (!empty($value) && !strtotime($value)) {
            $this->errors[$field] = "Invalid date format.";
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstError(): string {
        return !empty($this->errors) ? reset($this->errors) : '';
    }
}
