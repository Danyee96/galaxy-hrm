import{r as h,j as e}from"./ui-ZNezDwJH.js";import{P as G}from"./page-template-CTIeyLjS.js";import{u as J,c as K,r as Q,C as p,v as m,w as x,q as u}from"./app-YFJJ0OrI.js";import{A as V}from"./arrow-left-BQcd9TG4.js";import{P as X}from"./printer-CKilMq3t.js";import{U as Y}from"./users-qw3WasW0.js";import{D as g}from"./dollar-sign-BQgglRo_.js";import"./textarea-vttIlGX4.js";import"./select-CsqePxuC.js";import"./index-BdQq_4o_.js";import"./index-DTCOoIo4.js";import"./chevron-up-g67nOGOx.js";import"./react-country-flag.esm-qk2vEKSA.js";import"./language-BbwPiY59.js";import"./ticket-rdplzgka.js";import"./loader-circle-Bma05XDE.js";import"./copy-BZbsbUD1.js";import"./CookieConsentBanner-BsjoJ6T3.js";import"./switch-DGDhCeQB.js";import"./chevron-right-Di6fdvbm.js";import"./credit-card-DdQAJvzz.js";import"./settings-D5EzNPbx.js";import"./coins-1-Tn4WMY.js";import"./chart-no-axes-column-increasing-BeSU-xCD.js";import"./user-BSeZvBTh.js";import"./use-favicon-xuOcde2Q.js";import"./use-brand-theme-BX7C4MTb.js";/* empty css            *//* empty css                  */import"./utils-CkqlEBwS.js";function De(){var _,S,v,k,L,P,D;const{t:l}=J(),{payrollRun:o,holidayExclusions:b=[]}=K().props,d=h.useRef(null),c=t=>Math.round(Number(t||0)).toLocaleString("en-US"),f=t=>{const a=Math.round(Number(t||0));return a>0?`-${a.toLocaleString("en-US")}`:"0"},z=t=>{const a=t==null||t===""?"":String(t);if(!a)return"-";const r=a.match(/\d+/);return r?String(Number(r[0])):a},R=t=>{const a=Number(t||0);if(a<=0)return"0m";const r=Math.floor(a/60),n=a%60;return r>0?`${r}h ${n}m`:`${n}m`},F=t=>{const a=Number(t||0);return Number.isInteger(a)?String(a):a.toFixed(2)},$=t=>{var a,r,n,s;return((a=t.employee)==null?void 0:a.name)||((n=(r=t.employee)==null?void 0:r.employee)==null?void 0:n.name)||((s=t.employee)==null?void 0:s.full_name)||"-"},H=t=>{var a,r,n,s,i;return((n=(r=(a=t.employee)==null?void 0:a.employee)==null?void 0:r.department)==null?void 0:n.name)||((i=(s=t.employee)==null?void 0:s.department)==null?void 0:i.name)||"-"},j=t=>{var a;return Number(((a=t.deductions_breakdown)==null?void 0:a["Manual Fine Amount"])||0)},w=t=>{var n;const a=Number(((n=t.deductions_breakdown)==null?void 0:n["Partial Attendance Hours"])||0),r=j(t);return a>0&&Number(t.other_deductions||0)>r},B=t=>{var n,s;const a=Number(((n=t.deductions_breakdown)==null?void 0:n["Late Minutes"])||0),r=Number(((s=t.deductions_breakdown)==null?void 0:s["Partial Attendance Hours"])||0);return w(t)?a+r*60:a},U=t=>{var n;const a=Number(t.late_penalty||0),r=Number(((n=t.deductions_breakdown)==null?void 0:n["Partial Attendance Penalty"])||0);return w(t)?a+r:a},q=o.payroll_entries||[],y=h.useCallback(()=>{const t=document.documentElement,a=document.body,r=t.classList.contains("dark"),n=a.classList.contains("dark");d.current&&window.clearTimeout(d.current);const s=()=>{t.classList.remove("payroll-printing"),a.classList.remove("payroll-printing"),t.style.colorScheme="",a.style.colorScheme="",t.classList.toggle("dark",r),a.classList.toggle("dark",n),window.removeEventListener("afterprint",i),window.removeEventListener("focus",i),d.current=null},i=()=>{d.current&&window.clearTimeout(d.current),d.current=window.setTimeout(s,350)};t.classList.add("payroll-printing"),a.classList.add("payroll-printing"),t.classList.remove("dark"),a.classList.remove("dark"),t.style.colorScheme="light",a.style.colorScheme="light",window.addEventListener("afterprint",i,{once:!0}),window.addEventListener("focus",i,{once:!0}),window.setTimeout(()=>{window.print()},50)},[]);h.useEffect(()=>()=>{d.current&&window.clearTimeout(d.current)},[]),h.useEffect(()=>{new URLSearchParams(window.location.search).get("print")==="1"&&window.setTimeout(y,500)},[y]);const I=[{title:l("Dashboard"),href:route("dashboard")},{title:l("Payroll Management"),href:route("hr.payroll-runs.index")},{title:l("Payroll Runs"),href:route("hr.payroll-runs.index")},{title:o.title}],N=[{label:l("Back to List"),icon:e.jsx(V,{className:"h-4 w-4 mr-2"}),variant:"outline",onClick:()=>Q.get(route("hr.payroll-runs.index"))}];o.status==="completed"&&N.unshift({label:l("Print"),icon:e.jsx(X,{className:"h-4 w-4 mr-2"}),variant:"default",onClick:y});const O=t=>{const a={draft:"bg-gray-50 text-gray-700 ring-gray-600/20",processing:"bg-yellow-50 text-yellow-700 ring-yellow-600/20",completed:"bg-green-50 text-green-700 ring-green-600/20",cancelled:"bg-red-50 text-red-700 ring-red-600/20"};return a[t]||a.draft};return e.jsxs(G,{title:o.title,url:`/hr/payroll-runs/${o.id}`,actions:N,breadcrumbs:I,children:[e.jsx("style",{children:`
        html.payroll-printing,
        body.payroll-printing {
          background: #ffffff !important;
          color: #111827 !important;
          color-scheme: light !important;
        }
        html.payroll-printing [data-slot="sidebar-inset"],
        html.payroll-printing main {
          background: #ffffff !important;
          color: #111827 !important;
        }
        .payroll-entries-card,
        .payroll-entries-card [data-slot="card"],
        .payroll-entries-card [data-slot="card-content"],
        .payroll-entries-card > div,
        .payroll-entries-card table,
        .payroll-entries-card thead,
        .payroll-entries-card tbody,
        .payroll-entries-card tr,
        .payroll-entries-card th,
        .payroll-entries-card td,
        html.payroll-printing .payroll-entries-card,
        html.payroll-printing .payroll-entries-card [data-slot="card"],
        html.payroll-printing .payroll-entries-card [data-slot="card-content"],
        html.payroll-printing .payroll-entries-card > div,
        html.payroll-printing .payroll-entries-card table,
        html.payroll-printing .payroll-entries-card thead,
        html.payroll-printing .payroll-entries-card tbody,
        html.payroll-printing .payroll-entries-card tr,
        html.payroll-printing .payroll-entries-card th,
        html.payroll-printing .payroll-entries-card td {
          background-color: #ffffff !important;
          color-scheme: light !important;
        }
        .payroll-entries-card thead tr,
        html.payroll-printing .payroll-entries-card thead tr {
          background-color: #f8fafc !important;
        }
        .payroll-slip-grid {
          display: grid;
          grid-template-columns: 1fr;
          gap: 16px;
        }
        @media (min-width: 1024px) {
          .payroll-slip-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
          }
        }
        .payroll-slip {
          break-inside: avoid;
          page-break-inside: avoid;
          overflow: hidden;
          border: 1px dashed #94a3b8;
          border-radius: 8px;
          background: #ffffff;
          padding: 10px;
        }
        .payroll-slip-table {
          width: 100%;
          table-layout: fixed;
          border-collapse: collapse;
          overflow: hidden;
          border: 2px solid #111827;
          font-size: 15px;
          color: #111827;
        }
        .payroll-slip-table th,
        .payroll-slip-table td {
          border: 1px solid #111827;
          padding: 8px 10px;
          vertical-align: middle;
        }
        .payroll-slip-table th {
          background: #ffffff;
          color: #111827;
          text-align: center;
          font-weight: 800;
        }
        .payroll-slip-table .payroll-slip-employee {
          background: #ffffff;
          color: #050505;
          text-transform: uppercase;
          letter-spacing: 0;
          font-size: 24px;
          line-height: 1.15;
          padding: 18px 10px;
        }
        .payroll-slip-table .payroll-slip-total-head {
          background: #ffffff;
          color: #050505;
          font-size: 18px;
          line-height: 1.2;
        }
        .payroll-slip-table .payroll-slip-total-head span {
          display: block;
          color: #22c55e;
          font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
          font-size: 16px;
          font-weight: 900;
        }
        .payroll-slip-table .payroll-slip-meta td {
          background: #f8fafc;
          color: #475569;
          font-size: 13px;
          font-weight: 700;
          text-align: center;
        }
        .payroll-slip-table td:first-child {
          width: 34%;
          font-weight: 800;
          text-align: center;
        }
        .payroll-slip-table td:nth-child(2) {
          width: 36%;
          text-align: center;
          font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
          color: #374151;
          font-size: 15px;
        }
        .payroll-slip-table .amount {
          width: 30%;
          text-align: center;
          font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
          font-weight: 800;
          font-size: 15px;
          color: #111827;
        }
        .payroll-slip-table .danger {
          color: #dc2626;
        }
        .payroll-slip-table .success {
          color: #16a34a;
        }
        .payroll-slip-total td {
          background: #050505;
          color: #ffffff !important;
          font-size: 17px;
          font-weight: 900;
          text-align: center;
        }
        .payroll-slip-total td:last-child {
          background: #12b85f;
          color: #ffffff !important;
          font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        }
        @media print {
          html, body, #app, main, [data-slot="sidebar-inset"] {
            background: #fff !important;
            color: #111827 !important;
            color-scheme: light !important;
          }
          @page {
            size: A4 portrait;
            margin: 8mm;
          }
          aside, nav, header, [data-sidebar], .no-print {
            display: none !important;
          }
          main, body {
            background: #fff !important;
          }
          .print\\:shadow-none {
            box-shadow: none !important;
          }
          .payroll-entries-card {
            border: 0 !important;
            box-shadow: none !important;
          }
          .payroll-entries-card [data-slot="card-header"] {
            padding: 0 0 5mm 0 !important;
          }
          .payroll-entries-card [data-slot="card-content"] {
            padding: 0 !important;
          }
          .payroll-slip-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6mm;
          }
          .payroll-slip {
            border: 1px dashed #64748b;
            border-radius: 0;
            padding: 2mm;
          }
          .payroll-slip-table {
            font-size: 10.5px;
          }
          .payroll-slip-table th,
          .payroll-slip-table td {
            padding: 3.5px 5px;
          }
          .payroll-slip-table .payroll-slip-employee {
            font-size: 17px;
            padding: 8px 5px;
          }
          .payroll-slip-table .payroll-slip-total-head {
            font-size: 13px;
          }
          .payroll-slip-table .payroll-slip-total-head span,
          .payroll-slip-table .amount,
          .payroll-slip-table td:nth-child(2) {
            font-size: 11px;
          }
          .payroll-slip-total td {
            font-size: 12px;
          }
        }
      `}),e.jsxs("div",{className:"no-print grid grid-cols-1 md:grid-cols-4 gap-6 mb-6",children:[e.jsxs(p,{children:[e.jsxs(m,{className:"flex flex-row items-center justify-between space-y-0 pb-2",children:[e.jsx(x,{className:"text-sm font-medium",children:l("Total Employees")}),e.jsx(Y,{className:"h-4 w-4 text-muted-foreground"})]}),e.jsx(u,{children:e.jsx("div",{className:"text-2xl font-bold",children:o.employee_count})})]}),e.jsxs(p,{children:[e.jsxs(m,{className:"flex flex-row items-center justify-between space-y-0 pb-2",children:[e.jsx(x,{className:"text-sm font-medium",children:l("Gross Pay")}),e.jsx(g,{className:"h-4 w-4 text-muted-foreground"})]}),e.jsx(u,{children:e.jsx("div",{className:"text-2xl font-bold text-green-600",children:(_=window.appSettings)==null?void 0:_.formatCurrency(o.total_gross_pay)})})]}),e.jsxs(p,{children:[e.jsxs(m,{className:"flex flex-row items-center justify-between space-y-0 pb-2",children:[e.jsx(x,{className:"text-sm font-medium",children:l("Total Deductions")}),e.jsx(g,{className:"h-4 w-4 text-muted-foreground"})]}),e.jsx(u,{children:e.jsx("div",{className:"text-2xl font-bold text-red-600",children:(S=window.appSettings)==null?void 0:S.formatCurrency(o.total_deductions)})})]}),e.jsxs(p,{children:[e.jsxs(m,{className:"flex flex-row items-center justify-between space-y-0 pb-2",children:[e.jsx(x,{className:"text-sm font-medium",children:l("Net Pay")}),e.jsx(g,{className:"h-4 w-4 text-muted-foreground"})]}),e.jsx(u,{children:e.jsx("div",{className:"text-2xl font-bold text-blue-600",children:(v=window.appSettings)==null?void 0:v.formatCurrency(o.total_net_pay)})})]})]}),e.jsxs(p,{className:"no-print mb-6",children:[e.jsx(m,{children:e.jsx(x,{children:l("Payroll Run Details")})}),e.jsxs(u,{children:[e.jsxs("div",{className:"grid grid-cols-1 md:grid-cols-2 gap-4",children:[e.jsxs("div",{children:[e.jsx("label",{className:"text-sm font-medium text-gray-500",children:l("Pay Period")}),e.jsxs("p",{className:"text-sm",children:[((k=window.appSettings)==null?void 0:k.formatDateTimeSimple(o.pay_period_start,!1))||new Date(o.pay_period_start).toLocaleDateString()," - ",((L=window.appSettings)==null?void 0:L.formatDateTimeSimple(o.pay_period_end,!1))||new Date(o.pay_period_end).toLocaleDateString()]})]}),e.jsxs("div",{children:[e.jsx("label",{className:"text-sm font-medium text-gray-500",children:l("Pay Date")}),e.jsx("p",{className:"text-sm",children:((P=window.appSettings)==null?void 0:P.formatDateTimeSimple(o.pay_date,!1))||new Date(o.pay_date).toLocaleDateString()})]}),e.jsxs("div",{children:[e.jsx("label",{className:"text-sm font-medium text-gray-500",children:l("Payroll Frequency")}),e.jsx("p",{className:"text-sm",children:e.jsx("span",{className:"inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10",children:o.payroll_frequency==="weekly"?l("Weekly"):o.payroll_frequency==="biweekly"?l("Bi-Weekly"):l("Monthly")})})]}),e.jsxs("div",{children:[e.jsx("label",{className:"text-sm font-medium text-gray-500",children:l("Status")}),e.jsx("p",{children:e.jsx("span",{className:`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${O(o.status)}`,children:l(o.status)})})]}),e.jsxs("div",{children:[e.jsx("label",{className:"text-sm font-medium text-gray-500",children:l("Created At")}),e.jsx("p",{className:"text-sm",children:((D=window.appSettings)==null?void 0:D.formatDateTimeSimple(o.created_at,!1))||new Date(o.created_at).toLocaleDateString()})]})]}),o.notes&&e.jsxs("div",{className:"mt-4",children:[e.jsx("label",{className:"text-sm font-medium text-gray-500",children:l("Notes")}),e.jsx("p",{className:"text-sm mt-1",children:o.notes})]}),b.length>0&&e.jsxs("div",{className:"mt-4",children:[e.jsx("label",{className:"text-sm font-medium text-gray-500",children:l("Holiday Exclusions")}),e.jsx("div",{className:"mt-2 flex flex-wrap gap-2",children:b.map(t=>{var a,r;return e.jsxs("span",{className:"inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20",children:[t.name||l("Holiday")," · ",((r=(a=window.appSettings)==null?void 0:a.formatDateTimeSimple)==null?void 0:r.call(a,t.date,!1))||t.date]},t.date)})})]})]})]}),e.jsxs(p,{className:"payroll-entries-card print:shadow-none",children:[e.jsx(m,{children:e.jsx(x,{children:l("Employee Payroll Entries")})}),e.jsx(u,{children:e.jsx("div",{className:"payroll-slip-grid",children:q.map((t,a)=>{var E,T,A,C;const r=z(((T=(E=t.employee)==null?void 0:E.employee)==null?void 0:T.employee_id)||((A=t.employee)==null?void 0:A.employee_id)||((C=t.employee)==null?void 0:C.id)),n=B(t),s=U(t),i=t.unpaid_leave_days||t.absent_days||0,M=j(t),W=Number(t.bonus_amount||0)>0;return e.jsx("div",{className:"payroll-slip",children:e.jsx("table",{className:"payroll-slip-table",children:e.jsxs("tbody",{children:[e.jsxs("tr",{className:"payroll-slip-head",children:[e.jsx("th",{className:"payroll-slip-employee",colSpan:2,children:$(t)}),e.jsxs("th",{className:"payroll-slip-total-head",children:[l("TOTAL"),e.jsx("span",{children:c(t.net_pay)})]})]}),e.jsxs("tr",{className:"payroll-slip-meta",children:[e.jsx("td",{children:l("Employee ID")}),e.jsx("td",{children:r}),e.jsx("td",{children:H(t)})]}),e.jsxs("tr",{children:[e.jsx("td",{children:l("LATE")}),e.jsx("td",{children:R(n)}),e.jsx("td",{className:"amount danger",children:f(s)})]}),e.jsxs("tr",{children:[e.jsx("td",{children:l("ABSENT")}),e.jsxs("td",{children:[F(i)," ",l("days")]}),e.jsx("td",{className:"amount danger",children:f(t.absence_deduction)})]}),e.jsxs("tr",{children:[e.jsx("td",{children:l("FINE")}),e.jsx("td",{children:c(M)}),e.jsx("td",{className:"amount danger",children:f(M)})]}),W&&e.jsxs("tr",{children:[e.jsx("td",{children:l("BONUS")}),e.jsx("td",{children:c(t.bonus_amount)}),e.jsx("td",{className:"amount success",children:c(t.bonus_amount)})]}),e.jsxs("tr",{children:[e.jsx("td",{children:l("Salary")}),e.jsx("td",{children:c(t.basic_salary)}),e.jsx("td",{className:"amount",children:c(t.basic_salary)})]}),e.jsxs("tr",{children:[e.jsx("td",{children:l("Advance")}),e.jsx("td",{children:c(t.advance_deduction)}),e.jsx("td",{className:"amount danger",children:f(t.advance_deduction)})]})]})})},t.id||`${t.employee_id}-${a}`)})})})]})]})}export{De as default};
