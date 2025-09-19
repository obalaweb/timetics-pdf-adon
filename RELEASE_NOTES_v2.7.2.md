# Timetics PDF Addon v2.7.2 Release Notes

## 🎨 UI Improvements

### Enhanced PDF Items Display
- **Added**: Service name display under service code and ICD code in PDF items section
- **Format**: Service name now appears in bold under the service code and ICD code
- **Example**: 
  ```
  Code 0190
  Z00.0
  Longevity Consultation
  ```
- **Impact**: Makes it much clearer what service was provided to the patient

### Cleaner Address Format
- **Removed**: Company registration number from PDF address section
- **Before**: 
  ```
  Dr Ben
  Company Registration No: 2024/748523/21
  MP0953814 – PR1153307
  Office A2, 1st floor Polo Village Offices
  Val de Vie, Paarl, Western Cape
  7636, South Africa
  ```
- **After**: 
  ```
  Dr Ben
  MP0953814 – PR1153307
  Office A2, 1st floor Polo Village Offices
  Val de Vie, Paarl, Western Cape
  7636, South Africa
  ```
- **Impact**: Cleaner, more professional appearance without unnecessary registration details

## 🔧 Technical Improvements

### PDF Template Updates
- **Enhanced**: Items table structure for better readability
- **Improved**: Service information hierarchy in PDF layout
- **Updated**: Test files to reflect new template structure

### Code Quality
- **Maintained**: All existing functionality while improving presentation
- **Preserved**: Data integrity and medical information display
- **Enhanced**: User experience with clearer service identification

## 📋 Files Changed
- `timetics-pdf-addon.php` - Main plugin file with PDF template updates
- `cli-test.php` - Updated test template to include service name display

## 🚀 Installation
This is a minor release focusing on UI improvements. No breaking changes or database updates required.

## 🔄 Migration Notes
- No database changes required
- No configuration changes needed
- Existing PDFs will continue to work
- New PDFs will display improved layout with service names

## 🧪 Testing
- All existing functionality preserved
- Service name display working correctly
- Address format updated across all templates
- PDF generation and email attachment functioning properly
- Test suite updated to reflect new template structure

## 📊 What's New in PDF Layout

### Items Section Enhancement
The PDF items section now provides a clearer hierarchy:
1. **Service Code** (e.g., "Code 0190")
2. **ICD Code** (e.g., "Z00.0") 
3. **Service Name** (e.g., "Longevity Consultation") - **NEW**

### Address Section Cleanup
The company address section is now more streamlined:
- Removed company registration number
- Maintained practitioner and practice numbers
- Kept all essential contact information

## 🎯 Benefits
- **Better Clarity**: Patients can easily identify the service they received
- **Professional Appearance**: Cleaner address format looks more professional
- **Improved UX**: Enhanced readability of invoice details
- **Consistent Branding**: Maintains "Dr Ben" branding throughout

## 📞 Support
For any issues or questions, please refer to the developer documentation or contact support.

---
**Release Date**: January 2025  
**Version**: 2.7.2  
**Type**: UI Enhancement Release
