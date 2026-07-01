// components/simple-multi-select.tsx
import React, { useState, useRef, useEffect } from 'react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

type Option = {
  value: string;
  label: string;
};

type MultiSelectProps = {
  options: Option[];
  selected: string[];
  onChange: (selected: string[]) => void;
  placeholder?: string;
  className?: string;
  dropdownHeight?: string;
};

export function SimpleMultiSelect({
  options,
  selected,
  onChange,
  placeholder = "Select options",
  className,
  dropdownHeight = '320px',
}: MultiSelectProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);
  
  // Handle click outside to close dropdown
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);
  
  // Handle selection
  const handleSelect = (value: string) => {
    if (!selected.includes(value)) {
      onChange([...selected, value]);
    }
    setSearchTerm('');
    // Keep focus on the input
    setTimeout(() => {
      const input = containerRef.current?.querySelector('input');
      if (input) input.focus();
    }, 10);
  };
  
  // Handle removal
  const handleRemove = (value: string) => {
    onChange(selected.filter(item => item !== value));
  };
  
  // Filter options based on search term
  const filteredOptions = options.filter(option => 
    // Allow showing already selected options when searching
    (searchTerm || !selected.includes(option.value)) && 
    option.label.toLowerCase().includes(searchTerm.toLowerCase())
  );
  
  return (
    <div ref={containerRef} className={`relative w-full max-w-full ${className}`}>
      <div 
        className="flex flex-wrap items-center gap-2 p-3 border border-slate-200 bg-white rounded-2xl min-h-[60px] cursor-text w-full shadow-sm transition focus-within:border-slate-400"
        onClick={() => setIsOpen(true)}
      >
        {selected.map(value => {
          const option = options.find(o => o.value === value);
          return (
            <span
              key={value}
              className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700"
            >
              {option?.label || value}
              <button
                type="button"
                className="inline-flex items-center justify-center rounded-full p-1 hover:bg-slate-200"
                onClick={(e) => {
                  e.stopPropagation();
                  handleRemove(value);
                }}
              >
                <X className="h-3 w-3 text-slate-600" />
              </button>
            </span>
          );
        })}
        <input
          type="text"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          onFocus={() => setIsOpen(true)}
          placeholder={selected.length === 0 ? placeholder : ''}
          className="flex-1 outline-none bg-transparent min-w-[150px] w-full text-sm text-slate-700 placeholder:text-slate-400"
        />
      </div>
      
      {isOpen && (
        <div
          className="absolute z-50 w-full max-w-full mt-2 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-y-auto divide-y divide-slate-200"
          style={{ height: dropdownHeight, maxHeight: '70vh' }}
        >
          {filteredOptions.length > 0 ? filteredOptions.map(option => (
            <div
              key={option.value}
              className="px-4 py-3 text-base font-medium text-slate-900 cursor-pointer hover:bg-slate-50 transition-colors whitespace-normal break-words"
              onClick={() => {
                handleSelect(option.value);
                setIsOpen(true); // Keep dropdown open after selection
              }}
            >
              {option.label}
            </div>
          )) : (
            <div className="px-4 py-4 text-sm text-slate-500">
              No employees found.
            </div>
          )}
        </div>
      )}
    </div>
  );
}
