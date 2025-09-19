# Timetics PDF Addon v2.7.1 Release Notes

## 🐛 Bug Fixes

### Critical Fix: Staff Method Error
- **Fixed**: Fatal error `Call to undefined method Staff::get_name()`
- **Solution**: Changed to use `Staff::get_full_name()` method which is the correct method in the Timetics Staff class
- **Impact**: Resolves PDF generation failures that were causing fatal errors

### Branding Update
- **Updated**: Company name from "Performance MD Inc" to "Dr Ben" across all files
- **Files Updated**: 
  - Main plugin file (`timetics-pdf-addon.php`)
  - Email parser (`includes/StructuredEmailParser.php`)
  - All test files and templates
- **Impact**: All generated PDFs now display the correct company branding

## 🔧 Technical Improvements

### Code Quality
- **Enhanced**: Error handling for Staff object methods
- **Improved**: Consistent branding across all components
- **Updated**: Version constants and documentation

### Documentation
- **Added**: Comprehensive developer documentation (`DEVELOPER_DOCUMENTATION.md`)
- **Included**: Maintenance guidelines and troubleshooting steps
- **Covered**: Architecture overview and workflow explanation

## 📋 Files Changed
- `timetics-pdf-addon.php` - Main plugin file with version bump and Staff method fix
- `includes/StructuredEmailParser.php` - Updated company name
- `cli-test.php` - Updated branding
- `invoice-test.html` - Updated branding
- `test.php` - Updated branding
- `index.html` - Updated branding
- `pdf.php` - Updated branding

## 🚀 Installation
This is a patch release that fixes critical issues. Users experiencing PDF generation failures should update immediately.

## 🔄 Migration Notes
- No database changes required
- No configuration changes needed
- Existing PDFs will continue to work
- New PDFs will display updated branding

## 🧪 Testing
- All existing functionality preserved
- Staff information now displays correctly
- Medical information extraction continues to work as expected
- PDF generation and email attachment functioning properly

## 📞 Support
For any issues or questions, please refer to the developer documentation or contact support.

---
**Release Date**: January 2025  
**Version**: 2.7.1  
**Type**: Bug Fix Release
