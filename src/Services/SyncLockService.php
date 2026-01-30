<?php

namespace Src\Services;

/**
 * Service to prevent circular sync between Inventory and Accounting.
 * Uses static storage to track which entities are being synced from external sources.
 */
class SyncLockService
{
    /**
     * Track entities currently being synced from Accounting (back-sync).
     * Prevents Inventory observers from triggering re-sync to Accounting.
     */
    private static array $lockedEntities = [];

    /**
     * Lock an entity to prevent circular sync.
     * Should be called before updating entity data from Accounting back-sync.
     */
    public static function lock(string $type, int $id): void
    {
        $key = "{$type}:{$id}";
        self::$lockedEntities[$key] = true;
    }

    /**
     * Unlock an entity after sync is complete.
     */
    public static function unlock(string $type, int $id): void
    {
        $key = "{$type}:{$id}";
        unset(self::$lockedEntities[$key]);
    }

    /**
     * Check if an entity is currently locked (being synced from external source).
     */
    public static function isLocked(string $type, int $id): bool
    {
        $key = "{$type}:{$id}";
        return isset(self::$lockedEntities[$key]);
    }

    /**
     * Lock a project
     */
    public static function lockProject(int $projectId): void
    {
        self::lock('project', $projectId);
    }

    /**
     * Unlock a project
     */
    public static function unlockProject(int $projectId): void
    {
        self::unlock('project', $projectId);
    }

    /**
     * Check if a project is locked
     */
    public static function isProjectLocked(int $projectId): bool
    {
        return self::isLocked('project', $projectId);
    }

    /**
     * Lock a PoDeposit
     */
    public static function lockPoDeposit(int $poDepositId): void
    {
        self::lock('po_deposit', $poDepositId);
    }

    /**
     * Unlock a PoDeposit
     */
    public static function unlockPoDeposit(int $poDepositId): void
    {
        self::unlock('po_deposit', $poDepositId);
    }

    /**
     * Check if a PoDeposit is locked
     */
    public static function isPoDepositLocked(int $poDepositId): bool
    {
        return self::isLocked('po_deposit', $poDepositId);
    }

    /**
     * Lock a Product
     */
    public static function lockProduct(int $productId): void
    {
        self::lock('product', $productId);
    }

    /**
     * Unlock a Product
     */
    public static function unlockProduct(int $productId): void
    {
        self::unlock('product', $productId);
    }

    /**
     * Check if a Product is locked
     */
    public static function isProductLocked(int $productId): bool
    {
        return self::isLocked('product', $productId);
    }

    /**
     * Clear all locks. Useful for testing or cleanup.
     */
    public static function clearAll(): void
    {
        self::$lockedEntities = [];
    }
}
