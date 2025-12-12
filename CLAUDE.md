1. Using Laravel 10, PHP 8.1.
2. Always use proper import statements (use App\Models\User;), never inline class paths.
3. Always wrap DB write operations in try/catch with DB::beginTransaction(), commit(), and rollBack().
4. Always log actions: Log::info(), Log::warning(), Log::error().
5. Prefer service classes; keep controllers thin.
6. Use clean, PSR-12 formatting.
7. Access control:
Role 1: Superadmin
- staff_department_id = 16
- Can access all features
- Can view all referrals from all outlets and business units

Role 2: Admin
- Belongs to a business unit; has outlets
- Can access all features
- Can view referrals only from their own outlets (all outlets under their business unit)

Role 0: Normal User
- Belongs to business unit and outlet
- Can view referrals only from their own outlet
- Can create and update referrals
- Read-only for:
  • external referee
  • external organization
  • export CSV
  • form
  • form details
