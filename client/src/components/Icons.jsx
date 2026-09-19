import {
  Activity, ArrowRight, BarChart3, Bell, BriefcaseBusiness, Calculator, CheckCircle2, ChevronDown,
  ChevronLeft, ChevronRight, CircleDollarSign, ClipboardList, FileCheck2, FileText, FolderOpen,
  Copy, Download, Globe2, Headphones, Home, LayoutDashboard, LogOut, Menu, MessageCircle, MoreHorizontal,
  Mail, Pencil, Phone, Plus, QrCode, Search, Settings, ShieldCheck, Sparkles, Tag, Trash2, TrendingUp, Upload,
  User, UserCircle2, Users, WalletCards, X, Zap
} from 'lucide-react';

const map = { activity: Activity, arrow: ArrowRight, bar: BarChart3, bell: Bell, briefcase: BriefcaseBusiness, calculator: Calculator, check: CheckCircle2, chevronDown: ChevronDown, chevronLeft: ChevronLeft, chevronRight: ChevronRight, copy: Copy, download: Download, money: CircleDollarSign, revenue: CircleDollarSign, paid: CheckCircle2, orders: ClipboardList, invoice: FileCheck2, completed: FileCheck2, services: BriefcaseBusiness, notifications: Bell, file: FileText, folder: FolderOpen, globe: Globe2, support: Headphones, headphones: Headphones, phone: Phone, mail: Mail, home: Home, dashboard: LayoutDashboard, logout: LogOut, menu: Menu, chat: MessageCircle, more: MoreHorizontal, edit: Pencil, plus: Plus, qr: QrCode, search: Search, settings: Settings, shield: ShieldCheck, sparkles: Sparkles, tag: Tag, trash: Trash2, trending: TrendingUp, upload: Upload, user: User, userCircle: UserCircle2, users: Users, wallet: WalletCards, close: X, zap: Zap };

export default function Icon({ name = 'sparkles', size = 18, strokeWidth = 1.9, ...props }) {
  const Component = map[name] || Sparkles;
  return <Component size={size} strokeWidth={strokeWidth} {...props} />;
}
