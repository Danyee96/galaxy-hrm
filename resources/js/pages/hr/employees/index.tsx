import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { CrudTable } from '@/components/CrudTable';
import { PageTemplate } from '@/components/page-template';
import { toast } from '@/components/custom-toast';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { hasPermission } from '@/utils/authorization';
import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function Employees() {
  const { t } = useTranslation();
  const { auth, employees, departments, planLimits, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [selectedDepartment, setSelectedDepartment] = useState(pageFilters.department || 'all');
  const [showFilters, setShowFilters] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);

  const hasActiveFilters = () => selectedDepartment !== 'all' || searchTerm !== '';
  const activeFilterCount = () => (selectedDepartment !== 'all' ? 1 : 0) + (searchTerm ? 1 : 0);

  const filters = {
    page: 1,
    search: searchTerm || undefined,
    department: selectedDepartment !== 'all' ? selectedDepartment : undefined,
    per_page: pageFilters.per_page,
  };

  const applyFilters = () => {
    router.get(route('hr.employees.index'), filters, { preserveState: true, preserveScroll: true });
  };

  const handleSearch = (event: React.FormEvent) => {
    event.preventDefault();
    applyFilters();
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';

    router.get(
      route('hr.employees.index'),
      { ...filters, sort_field: field, sort_direction: direction },
      { preserveState: true, preserveScroll: true },
    );
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedDepartment('all');
    setShowFilters(false);
    router.get(route('hr.employees.index'), { page: 1, per_page: pageFilters.per_page }, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: any) => {
    setCurrentItem(item);

    if (action === 'edit') {
      router.get(route('hr.employees.edit', item.employee?.id || item.id));
    }

    if (action === 'delete') {
      setIsDeleteModalOpen(true);
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting employee...'));

    router.delete(route('hr.employees.destroy', currentItem.id), {
      onSuccess: (page) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        toast.error(typeof errors === 'string' ? t(errors) : t('Failed to delete employee'));
      },
    });
  };

  const pageActions = [];
  if (hasPermission(permissions, 'create-employees')) {
    const canCreate = !planLimits || planLimits.can_create;
    pageActions.push({
      label: canCreate ? t('Add Employee') : t('Employee Limit Reached'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: canCreate ? 'default' : 'outline',
      onClick: canCreate
        ? () => router.get(route('hr.employees.create'))
        : () => toast.error(t('Employee limit exceeded. Please upgrade your plan.')),
      disabled: !canCreate,
    });
  }

  const departmentOptions = [
    { value: 'all', label: t('All Departments') },
    ...(departments || []).map((department: any) => ({
      value: department.id.toString(),
      label: department.name,
    })),
  ];

  const formatEmployeeNumber = (value: any) => {
    const raw = value == null || value === '' ? '' : String(value);
    if (!raw) return '-';
    const match = raw.match(/\d+/);
    return match ? String(Number(match[0])) : raw;
  };

  const formatAmount = (value: any) => {
    if (value == null || value === '') return '-';

    const amount = Number(value);
    if (Number.isNaN(amount)) return value;

    return Number.isInteger(amount) ? String(amount) : String(amount);
  };

  const formatWhatsAppNumber = (value: any) => {
    const digits = String(value || '').replace(/\D/g, '');
    if (!digits) return '-';
    if (digits.startsWith('0092')) return `+${digits.slice(2)}`;
    if (digits.startsWith('92')) return `+${digits}`;
    if (digits.startsWith('0')) return `+92${digits.slice(1)}`;
    return `+92${digits}`;
  };

  const columns = [
    {
      key: 'employee_id',
      label: t('Employee ID'),
      sortable: true,
      render: (_value: any, row: any) => formatEmployeeNumber(row.employee?.employee_id),
    },
    {
      key: 'name',
      label: t('Name'),
      sortable: true,
      render: (_value: any, row: any) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: 'department',
      label: t('Department'),
      render: (_value: any, row: any) => row.employee?.department?.name || '-',
    },
    {
      key: 'whatsapp_number',
      label: t('WhatsApp'),
      render: (_value: any, row: any) => formatWhatsAppNumber(row.employee?.whatsapp_number),
    },
    {
      key: 'salary',
      label: t('Salary'),
      render: (_value: any, row: any) => formatAmount(row.employee?.base_salary),
    },
    {
      key: 'bonus',
      label: t('Bonus'),
      render: (_value: any, row: any) => formatAmount(row.profile_bonus),
    },
  ];

  const actions = [
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500',
      requiredPermission: 'edit-employees',
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500',
      requiredPermission: 'delete-employees',
    },
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Employees') },
  ];

  return (
    <PageTemplate
      title={t('Employees')}
      url="/hr/employees"
      actions={pageActions}
      breadcrumbs={breadcrumbs}
      noPadding
    >
      <div className="mb-4 rounded-lg bg-white p-4 shadow">
        <SearchAndFilterBar
          searchTerm={searchTerm}
          onSearchChange={setSearchTerm}
          onSearch={handleSearch}
          filters={[
            {
              name: 'department',
              label: t('Department'),
              type: 'select',
              value: selectedDepartment,
              onChange: setSelectedDepartment,
              options: departmentOptions,
              searchable: true,
            },
          ]}
          showFilters={showFilters}
          setShowFilters={setShowFilters}
          hasActiveFilters={hasActiveFilters}
          activeFilterCount={activeFilterCount}
          onResetFilters={handleResetFilters}
          onApplyFilters={applyFilters}
          currentPerPage={pageFilters.per_page?.toString() || '10'}
          onPerPageChange={(value) => {
            router.get(
              route('hr.employees.index'),
              { ...filters, per_page: parseInt(value) },
              { preserveState: true, preserveScroll: true },
            );
          }}
        />
      </div>

      <div className="overflow-hidden rounded-lg bg-white shadow">
        <CrudTable
          columns={columns}
          actions={actions}
          data={employees?.data || []}
          from={employees?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'view-employees',
            create: 'create-employees',
            edit: 'edit-employees',
            delete: 'delete-employees',
          }}
        />

        <Pagination
          from={employees?.from || 0}
          to={employees?.to || 0}
          total={employees?.total || 0}
          links={employees?.links}
          entityName={t('employees')}
          onPageChange={(url) => router.get(url)}
        />
      </div>

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="employee"
      />
    </PageTemplate>
  );
}
