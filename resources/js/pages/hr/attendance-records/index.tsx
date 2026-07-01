import { useRef, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Clock, Download, LogIn, LogOut, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function AttendanceRecords() {
  const { t } = useTranslation();
  const { auth, attendanceDays = [], attendanceAnalytics = {}, employees = [], selectedEmployee, filters: pageFilters = {} } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const [selectedEmployeeId, setSelectedEmployeeId] = useState(pageFilters.employee_id?.toString() || employees?.[0]?.id?.toString() || '');
  const selectedEmployeeObject = employees.find((employee: any) => employee.id.toString() === selectedEmployeeId);
  const selectedEmployeeName = selectedEmployeeObject?.name || selectedEmployee?.name || '';
  const formatEmployeeNumber = (value: any) => {
    const raw = value == null || value === '' ? '' : String(value);
    if (!raw) return '-';
    const match = raw.match(/\d+/);
    return match ? String(Number(match[0])) : raw;
  };
  const selectedEmployeeDisplay = selectedEmployeeObject
    ? `${selectedEmployeeObject.name} (${formatEmployeeNumber(selectedEmployeeObject.employee_id || selectedEmployeeObject.employee?.employee_id)})`
    : selectedEmployee?.name
      ? `${selectedEmployee.name} (${formatEmployeeNumber(selectedEmployee.employee_id || selectedEmployee.employee?.employee_id)})`
      : t('Employee');
  const [employeeSearch, setEmployeeSearch] = useState(selectedEmployeeName);
  const [isEmployeeMenuOpen, setIsEmployeeMenuOpen] = useState(false);
  const currentMonth = () => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  };
  const [selectedMonth, setSelectedMonth] = useState(pageFilters.month || currentMonth());
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
  const importInputRef = useRef<HTMLInputElement>(null);

  const applyFilters = (employeeId = selectedEmployeeId, month = selectedMonth) => {
    router.get(route('hr.attendance-records.index'), {
      employee_id: employeeId || undefined,
      month: month || undefined,
    }, { preserveState: true, preserveScroll: true });
  };

  const moveMonth = (delta: number) => {
    const [year, month] = selectedMonth.split('-').map(Number);
    const date = new Date(year, month - 1 + delta, 1);
    const nextMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    setSelectedMonth(nextMonth);
    applyFilters(selectedEmployeeId, nextMonth);
  };

  const formatTime = (value?: string | null) => {
    if (!value) return '-';
    return window.appSettings?.formatTime?.(value) || value;
  };

  const formatHours = (value: number | string | null | undefined) => `${Number(value || 0).toFixed(2)}h`;

  const formatMinutes = (minutes: number | string | null | undefined) => {
    const total = Number(minutes || 0);
    if (total <= 0) return '0m';
    const hours = Math.floor(total / 60);
    const mins = total % 60;
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
  };

  const statusBadge = (day: any) => {
    const configs: Record<string, string> = {
      present: 'bg-green-50 text-green-700 ring-green-600/20',
      absent: 'bg-red-50 text-red-700 ring-red-600/20',
      half_day: 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
      on_leave: 'bg-blue-50 text-blue-700 ring-blue-600/20',
      holiday: 'bg-purple-50 text-purple-700 ring-purple-600/20',
      no_clockout: 'bg-orange-50 text-orange-700 ring-orange-600/20',
      no_record: 'bg-gray-50 text-gray-700 ring-gray-600/20',
    };
    const labels: Record<string, string> = {
      present: t('Present'),
      absent: t('Absent'),
      half_day: t('Half Day'),
      on_leave: day.leave_type ? `${t('On Leave')} (${day.leave_type.name})` : t('On Leave'),
      holiday: day.holiday?.name ? `${t('Holiday')}: ${day.holiday.name}` : t('Holiday'),
      no_clockout: t('No Clockout'),
      no_record: t('No Record'),
    };

    return (
      <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${configs[day.status] || configs.no_record}`}>
        {labels[day.status] || day.status}
      </span>
    );
  };

  const handleAddNew = (day?: any) => {
    setCurrentItem({
      employee_id: selectedEmployeeId,
      date: day?.date || `${selectedMonth}-01`,
      break_hours: 0,
      status: 'present',
    });
    setFormMode('create');
    setIsFormModalOpen(true);
  };

  const handleEdit = (day: any) => {
    setCurrentItem(day);
    setFormMode('edit');
    setIsFormModalOpen(true);
  };

  const handleDelete = (day: any) => {
    setCurrentItem(day);
    setIsDeleteModalOpen(true);
  };

  const handleFormSubmit = (formData: any) => {
    const request = formMode === 'create'
      ? router.post
      : (url: string, data: any, options: any) => router.put(url, data, options);
    const url = formMode === 'create'
      ? route('hr.attendance-records.store')
      : route('hr.attendance-records.update', currentItem.id);

    toast.loading(formMode === 'create' ? t('Creating attendance record...') : t('Updating attendance record...'));
    request(url, formData, {
      onSuccess: (page: any) => {
        setIsFormModalOpen(false);
        toast.dismiss();
        if (page.props.flash?.success) toast.success(t(page.props.flash.success));
        if (page.props.flash?.error) toast.error(t(page.props.flash.error));
      },
      onError: (errors: any) => {
        toast.dismiss();
        toast.error(typeof errors === 'string' ? errors : Object.values(errors).join(', '));
      },
    });
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting attendance record...'));
    router.delete(route('hr.attendance-records.destroy', currentItem.id), {
      onSuccess: (page) => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (page.props.flash?.success) toast.success(t(page.props.flash.success));
        if (page.props.flash?.error) toast.error(t(page.props.flash.error));
      },
      onError: (errors) => {
        toast.dismiss();
        toast.error(typeof errors === 'string' ? errors : Object.values(errors).join(', '));
      },
    });
  };

  const handleImportSheet = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    toast.loading(t('Importing attendance sheet...'));

    router.post(route('hr.attendance-records.import-sheet'), formData, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash?.success) toast.success(t(page.props.flash.success));
        if (page.props.flash?.error) toast.error(t(page.props.flash.error));
      },
      onError: (errors: any) => {
        toast.dismiss();
        toast.error(typeof errors === 'string' ? errors : Object.values(errors).join(', '));
      },
      onFinish: () => {
        event.target.value = '';
      },
    });
  };

  const downloadAttendance = () => {
    window.location.href = route('hr.attendance-records.export', {
      employee_id: selectedEmployeeId || undefined,
      month: selectedMonth || undefined,
    });
  };

  const pageActions = [
    ...(hasPermission(permissions, 'manage-attendance-records')
      ? [
          { label: t('Download Attendance'), icon: <Download className="mr-2 h-4 w-4" />, onClick: downloadAttendance },
          { label: t('Import Attendance Sheet'), icon: <Upload className="mr-2 h-4 w-4" />, onClick: () => importInputRef.current?.click() },
        ]
      : []),
    ...(hasPermission(permissions, 'create-attendance-records')
      ? [{ label: t('Add Record'), icon: <Plus className="mr-2 h-4 w-4" />, onClick: () => handleAddNew() }]
      : []),
  ];

  const filteredEmployees = employees.filter((employee: any) =>
    employee.name.toLowerCase().includes(employeeSearch.toLowerCase())
  );

  const selectEmployee = (employee: any) => {
    const employeeId = employee.id.toString();
    setSelectedEmployeeId(employeeId);
    setEmployeeSearch(employee.name);
    setIsEmployeeMenuOpen(false);
    applyFilters(employeeId, selectedMonth);
  };

  const metricCards = [
    { label: t('Present'), value: attendanceAnalytics.present || 0, tone: 'text-green-700' },
    { label: t('Absent'), value: attendanceAnalytics.absent || 0, tone: 'text-red-700' },
    { label: t('No Clock In'), value: attendanceAnalytics.no_clockin || 0, tone: 'text-orange-700' },
    { label: t('Late Days'), value: attendanceAnalytics.late_days || 0, tone: 'text-amber-700' },
    { label: t('Total Late Time'), value: formatMinutes(attendanceAnalytics.total_late_minutes), tone: 'text-amber-700' },
    { label: t('Total Hours'), value: formatHours(attendanceAnalytics.total_hours), tone: 'text-slate-700' },
    { label: t('Overtime'), value: formatHours(attendanceAnalytics.overtime_hours), tone: 'text-indigo-700' },
  ];

  return (
    <PageTemplate
      title={t('Attendance Records')}
      url="/hr/attendance-records"
      actions={pageActions}
      breadcrumbs={[{ title: t('Dashboard'), href: route('dashboard') }, { title: t('Attendance Records') }]}
      noPadding
    >
      <input
        ref={importInputRef}
        type="file"
        accept=".xls,.xlsx,.xlsm,.xlt,.xltx,.xltm,.csv"
        className="hidden"
        onChange={handleImportSheet}
      />

      <div className="relative z-40 mb-4 rounded-lg bg-white p-4 shadow dark:bg-gray-900">
        <div className="grid gap-3 md:grid-cols-[minmax(220px,1fr)_180px_auto_auto_auto]">
          <div
            className="relative"
            onBlur={(event) => {
              if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
                setIsEmployeeMenuOpen(false);
                setEmployeeSearch(selectedEmployeeName);
              }
            }}
          >
            <Input
              value={employeeSearch}
              placeholder={t('Search Employee')}
              onFocus={(event) => {
                setIsEmployeeMenuOpen(true);
                event.currentTarget.select();
              }}
              onChange={(event) => {
                setEmployeeSearch(event.target.value);
                setIsEmployeeMenuOpen(true);
              }}
            />
            {isEmployeeMenuOpen && (
              <div className="absolute z-[100] mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-white p-1 shadow-lg dark:bg-gray-900">
                {filteredEmployees.length > 0 ? (
                  filteredEmployees.map((employee: any) => (
                    <button
                      key={employee.id}
                      type="button"
                      className="flex w-full items-center rounded-sm px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                      onMouseDown={(event) => {
                        event.preventDefault();
                        selectEmployee(employee);
                      }}
                    >
                      {employee.name} {employee.employee_id || employee.employee?.employee_id ? `(${formatEmployeeNumber(employee.employee_id || employee.employee?.employee_id)})` : ''}
                    </button>
                  ))
                ) : (
                  <div className="px-3 py-2 text-sm text-muted-foreground">{t('No employees found')}</div>
                )}
              </div>
            )}
          </div>
          <Input
            type="month"
            value={selectedMonth}
            onChange={(event) => {
              setSelectedMonth(event.target.value);
              applyFilters(selectedEmployeeId, event.target.value);
            }}
          />
          <Button type="button" variant="outline" onClick={() => moveMonth(-1)}>{t('Previous')}</Button>
          <Button type="button" variant="outline" onClick={() => moveMonth(1)}>{t('Next')}</Button>
          <Button type="button" onClick={() => applyFilters()}>{t('Apply')}</Button>
        </div>
      </div>

      <div className="relative z-10 mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        {metricCards.map((metric) => (
          <div key={metric.label} className="rounded-lg bg-white p-4 shadow dark:bg-gray-900">
            <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{metric.label}</div>
            <div className={`mt-2 text-2xl font-semibold ${metric.tone}`}>{metric.value}</div>
          </div>
        ))}
      </div>

      <div className="relative z-0 overflow-hidden rounded-lg bg-white shadow dark:bg-gray-900">
        <div className="flex items-center justify-between border-b px-4 py-3">
          <div>
            <div className="font-semibold">{selectedEmployeeDisplay}</div>
            <div className="text-sm text-muted-foreground">{selectedMonth}</div>
          </div>
          <div className="text-sm text-muted-foreground">
            {attendanceAnalytics.calendar_days || attendanceDays.length} {t('days')}
          </div>
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('Date')}</TableHead>
              <TableHead>{t('Day')}</TableHead>
              <TableHead>{t('Status')}</TableHead>
              <TableHead>{t('Duty')}</TableHead>
              <TableHead>{t('Clock In')}</TableHead>
              <TableHead>{t('Clock Out')}</TableHead>
              <TableHead>{t('Work Time')}</TableHead>
              <TableHead>{t('Late')}</TableHead>
              <TableHead>{t('Early')}</TableHead>
              <TableHead className="text-right">{t('Actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {attendanceDays.map((day: any) => (
              <TableRow key={day.date}>
                <TableCell className="font-medium">{window.appSettings?.formatDateTimeSimple?.(day.date, false) || day.date}</TableCell>
                <TableCell>{day.day_name}</TableCell>
                <TableCell>
                  <div className="flex flex-wrap gap-1">
                    {statusBadge(day)}
                    {day.holiday?.name && day.status !== 'holiday' && (
                      <span className="rounded-md bg-purple-50 px-2 py-1 text-xs text-purple-700 ring-1 ring-purple-600/20">
                        {t('Holiday')}: {day.holiday.name}
                      </span>
                    )}
                    {day.missing_clock_in && (
                      <span className="rounded-md bg-orange-50 px-2 py-1 text-xs text-orange-700 ring-1 ring-orange-600/20">
                        {t('No Clock In')}
                      </span>
                    )}
                    {day.is_weekend && <span className="rounded-md bg-slate-50 px-2 py-1 text-xs text-slate-700 ring-1 ring-slate-600/20">{t('Weekend')}</span>}
                  </div>
                </TableCell>
                <TableCell className="font-mono text-xs">{formatTime(day.on_duty)} - {formatTime(day.off_duty)}</TableCell>
                <TableCell><span className="font-mono text-green-700"><LogIn className="mr-1 inline h-3 w-3" />{formatTime(day.clock_in)}</span></TableCell>
                <TableCell><span className="font-mono text-red-700"><LogOut className="mr-1 inline h-3 w-3" />{formatTime(day.clock_out)}</span></TableCell>
                <TableCell><span className="font-mono"><Clock className="mr-1 inline h-3 w-3" />{formatHours(day.total_hours)}</span></TableCell>
                <TableCell className={day.late_minutes > 0 ? 'font-medium text-amber-700' : ''}>{formatMinutes(day.late_minutes)}</TableCell>
                <TableCell className={day.early_leave_minutes > 0 ? 'font-medium text-orange-700' : ''}>{formatMinutes(day.early_leave_minutes)}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-1">
                    {day.id && hasPermission(permissions, 'edit-attendance-records') && (
                      <Button type="button" variant="ghost" size="icon" className="h-8 w-8" onClick={() => handleEdit(day)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    )}
                    {day.id && hasPermission(permissions, 'delete-attendance-records') && (
                      <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-red-600" onClick={() => handleDelete(day)}>
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                    {!day.id && hasPermission(permissions, 'create-attendance-records') && (
                      <Button type="button" variant="ghost" size="sm" onClick={() => handleAddNew(day)}>
                        <Plus className="h-4 w-4" />
                        {t('Add')}
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          fields: [
            {
              name: 'employee_id',
              label: t('Employee'),
              type: 'select',
              required: true,
              searchable: true,
              options: employees.map((emp: any) => ({
                value: emp.id.toString(),
                label: `${emp.name}${emp.employee_id || emp.employee?.employee_id ? ` (${formatEmployeeNumber(emp.employee_id || emp.employee?.employee_id)})` : ''}`,
              })),
            },
            { name: 'date', label: t('Date'), type: 'date', required: true },
            { name: 'clock_in', label: t('Clock In Time'), type: 'time' },
            { name: 'clock_out', label: t('Clock Out Time'), type: 'time' },
            { name: 'break_hours', label: t('Break Hours'), type: 'number', min: 0, step: 0.5, defaultValue: 0 },
            {
              name: 'status',
              label: t('Status'),
              type: 'select',
              required: true,
              options: [
                { value: 'present', label: t('Present') },
                { value: 'absent', label: t('Absent') },
                { value: 'holiday', label: t('Holiday') },
              ],
            },
            { name: 'is_holiday', label: t('Holiday'), type: 'checkbox', defaultValue: false },
            { name: 'notes', label: t('Notes'), type: 'textarea' },
          ],
          modalSize: 'lg',
        }}
        initialData={currentItem}
        title={formMode === 'create' ? t('Add Attendance Record') : t('Edit Attendance Record')}
        mode={formMode}
      />

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={selectedEmployee?.name || ''}
        entityName="attendance record"
      />
    </PageTemplate>
  );
}
